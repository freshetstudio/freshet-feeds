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
use FreshetFeeds\Item\ItemCollection;
use FreshetFeeds\Provider\Mock\FixtureNormalizer;
use FreshetFeeds\Provider\MockProvider;
use FreshetFeeds\Provider\ProviderRegistry;
use FreshetFeeds\Rest\FeedsController;
use FreshetFeeds\Template\TemplateLoader;

final class Plugin
{
    private static ?self $instance = null;

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
        $this->feeds = new FeedRepository();
        $this->cache = new ItemCache();
        $this->providers = new ProviderRegistry();
        $this->runner = new FeedRunner($this->providers, $this->cache, new ImageStore(), new FetchLock());
        $this->cron = new Cron($this->feeds, $this->cache, $this->runner);
        $this->templates = new TemplateLoader(FRESHET_FEEDS_DIR . 'templates');

        add_action('init', [$this, 'onInit']);

        add_action('rest_api_init', fn () => (new FeedsController($this->feeds))->registerRoutes());

        $this->cron->hooks();
        (new FeedsPage($this->feeds, $this->providers, $this->cache, $this->runner))->hooks();

        // Anything a build carries beyond the above boots from one class. A
        // build without it — the wordpress.org one, where the file is not
        // there — has nothing to boot: the release build regenerates the
        // classmap after the strip, so the autoloader answers nothing, this is
        // one class_exists(), and everything above is whole as it stands.
        if (class_exists(Extension\Bootstrap::class)) {
            Extension\Bootstrap::boot();
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
}
