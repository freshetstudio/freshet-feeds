<?php

declare(strict_types=1);

namespace FreshetFeeds;

use FreshetFeeds\Admin\FeedsPage;
use FreshetFeeds\Blocks\FeedBlock;
use FreshetFeeds\Cache\ImageStore;
use FreshetFeeds\Cache\ItemCache;
use FreshetFeeds\Cli\FetchCommand;
use FreshetFeeds\Feed\Feed;
use FreshetFeeds\Feed\FeedRepository;
use FreshetFeeds\Fetch\Cron;
use FreshetFeeds\Fetch\FeedRunner;
use FreshetFeeds\Fetch\FetchLock;
use FreshetFeeds\Admin\LicenseSection;
use FreshetFeeds\Item\ItemCollection;
use FreshetFeeds\License\LicenseClient;
use FreshetFeeds\License\LicenseInterface;
use FreshetFeeds\License\RemoteLicense;
use FreshetFeeds\License\UpdateChecker;
use FreshetFeeds\Provider\Mock\FixtureNormalizer;
use FreshetFeeds\Provider\MockProvider;
use FreshetFeeds\Provider\ProviderRegistry;
use FreshetFeeds\Rest\FeedsController;
use FreshetFeeds\Template\TemplateLoader;

final class Plugin
{
    private static ?self $instance = null;

    private LicenseInterface $license;
    private FeedRepository $feeds;
    private ProviderRegistry $providers;
    private ItemCache $cache;
    private FeedRunner $runner;
    private Cron $cron;
    private TemplateLoader $templates;

    public static function boot(): self
    {
        return self::$instance ??= new self();
    }

    public static function instance(): self
    {
        return self::boot();
    }

    private function __construct()
    {
        // The wordpress.org build ships without the remote-license stack
        // (Guideline 5: no locked features) — feeds are unlimited in BOTH
        // builds. What a validating key buys (direct-sold builds only) is the
        // managed source pipeline: canUseProxy() gates that entitlement.
        $hasLicenseStack = is_readable(FRESHET_FEEDS_DIR . 'src/License/RemoteLicense.php');
        $licenseClient = $hasLicenseStack ? new LicenseClient() : null;

        /**
         * Filter the active license implementation.
         *
         * @param LicenseInterface $license
         */
        $this->license = apply_filters(
            'freshet_feeds_license',
            $hasLicenseStack ? new RemoteLicense($licenseClient) : new \FreshetFeeds\License\UnlimitedLicense()
        );

        $this->feeds = new FeedRepository();
        $this->cache = new ItemCache();
        $this->providers = new ProviderRegistry();
        $this->runner = new FeedRunner($this->providers, $this->cache, new ImageStore(), new FetchLock());
        $this->cron = new Cron($this->feeds, $this->cache, $this->runner);
        $this->templates = new TemplateLoader(FRESHET_FEEDS_DIR . 'templates');

        add_action('init', [$this, 'onInit']);

        // Translations shipped inside the plugin's own /languages need this
        // call — without a custom path the textdomain registry only looks in
        // WP_LANG_DIR, so wp.org-delivered translations load either way but a
        // bundled .mo never would. On init: nothing here translates earlier.
        add_action('init', static function (): void {
            load_plugin_textdomain(
                'freshet-feeds',
                false,
                dirname(plugin_basename(FRESHET_FEEDS_FILE)) . '/languages'
            );
        });

        add_action('rest_api_init', fn () => (new FeedsController($this->feeds))->registerRoutes());

        $licenseSection = $licenseClient !== null ? new LicenseSection($licenseClient, $this->license) : null;

        $this->cron->hooks();
        (new FeedsPage($this->feeds, $this->providers, $this->cache, $this->runner, $this->license, $licenseSection))->hooks();
        $licenseSection?->hooks();

        // Absent from the wordpress.org build (updates come from the directory);
        // present in direct-sold builds where it is opt-in via constant/filter.
        // File check (not class_exists): the optimized classmap in release
        // builds would emit a warning autoloading a stripped file.
        if ($licenseClient !== null && is_readable(FRESHET_FEEDS_DIR . 'src/License/UpdateChecker.php')) {
            (new UpdateChecker($licenseClient))->hooks();
        }

        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('freshet-feeds', new FetchCommand($this->feeds, $this->runner, $this->cache));
        }
    }

    public function onInit(): void
    {
        FeedRepository::registerPostType();
        $this->registerProviders();
        (new FeedBlock())->register();
    }

    private function registerProviders(): void
    {
        $rssNormalizer = new \FreshetFeeds\Provider\Rss\RssNormalizer();

        $this->providers->register(new \FreshetFeeds\Provider\Rss\RssProvider($rssNormalizer));
        $this->providers->register(new \FreshetFeeds\Provider\YouTube\YouTubeProvider($rssNormalizer));
        $this->providers->register(new \FreshetFeeds\Provider\Bluesky\BlueskyProvider(
            new \FreshetFeeds\Provider\Bluesky\BlueskyNormalizer(),
        ));

        $this->providers->register(new MockProvider(
            new FixtureNormalizer(),
            FRESHET_FEEDS_DIR . 'data/fixtures/mock-posts.json',
        ));

        /**
         * Register additional feed providers.
         *
         * @param ProviderRegistry $registry
         */
        do_action('freshet_feeds_register_providers', $this->providers);
    }

    /**
     * Loop API backing: serve cached items (stale is fine — a background
     * refresh gets queued), fetching synchronously only on a cold cache.
     */
    public function items(string $feedSlug): ItemCollection
    {
        $feed = $this->feeds->findBySlug($feedSlug);

        if ($feed === null) {
            return new ItemCollection();
        }

        $cached = $this->cache->get($feed);

        if ($cached === null) {
            // Cold start: the only path that blocks on the remote API.
            return ($this->runner->run($feed) ?? new ItemCollection())->take($feed->count);
        }

        if (!$this->cache->isFresh($feed)) {
            $this->cron->scheduleRefresh($feed->id);
        }

        return $cached->take($feed->count);
    }

    /** @param array{layout?: string, count?: int, wrapper_attributes?: string} $args */
    public function render(string $feedSlug, array $args = []): void
    {
        $feed = $this->feeds->findBySlug($feedSlug);

        if ($feed === null) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                printf(
                    '<!-- freshet-feeds: unknown feed "%s" -->',
                    esc_html($feedSlug)
                );
            }

            return;
        }

        $items = $this->items($feedSlug);

        if (isset($args['count']) && $args['count'] > 0) {
            $items = $items->take($args['count']);
        }

        $layout = $args['layout'] ?? '';
        $layout = $layout !== '' ? $layout : $feed->defaultLayout;

        $template = $items->isEmpty() ? 'empty' : 'feed';

        $this->templates->render($template, [
            'feed' => $feed,
            'items' => $items,
            'layout' => $layout,
            'args' => $args,
        ]);
    }

    public function templates(): TemplateLoader
    {
        return $this->templates;
    }

    public function feeds(): FeedRepository
    {
        return $this->feeds;
    }

    public function providerRegistry(): ProviderRegistry
    {
        return $this->providers;
    }

    public function itemCache(): ItemCache
    {
        return $this->cache;
    }

    public function feedRunner(): FeedRunner
    {
        return $this->runner;
    }

    public function license(): LicenseInterface
    {
        return $this->license;
    }
}
