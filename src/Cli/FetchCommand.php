<?php

declare(strict_types=1);

namespace FreshetFeeds\Cli;

use FreshetFeeds\Cache\ItemCache;
use FreshetFeeds\Feed\FeedRepository;
use FreshetFeeds\Fetch\FeedRunner;
use WP_CLI;

/**
 * wp freshet-feeds fetch <feed-slug> [--force]
 *
 * Smoke-tests the full pipeline (fetch → normalize → localize images → cache)
 * for any feed, mock or live.
 */
final class FetchCommand
{
    public function __construct(
        private readonly FeedRepository $feeds,
        private readonly FeedRunner $runner,
        private readonly ItemCache $cache,
    ) {
    }

    /**
     * Fetch a feed and print the cached items.
     *
     * ## OPTIONS
     *
     * <feed>
     * : Feed slug.
     *
     * [--force]
     * : Ignore the fetch lock.
     *
     * @param array{0: string} $args
     * @param array{force?: bool} $assocArgs
     */
    public function fetch(array $args, array $assocArgs): void
    {
        $feed = $this->feeds->findBySlug($args[0]);

        if ($feed === null) {
            WP_CLI::error(sprintf('Unknown feed "%s".', $args[0]));
        }

        $items = $this->runner->run($feed, force: isset($assocArgs['force']));

        if ($items === null) {
            WP_CLI::error($this->cache->lastError($feed) ?? 'Fetch skipped (locked) or failed.');
        }

        foreach ($items as $item) {
            WP_CLI::log(sprintf(
                '- [%s] %s%s %s',
                $item->date('Y-m-d'),
                $item->title() !== null ? $item->title() . ' — ' : '',
                $item->excerpt(12),
                $item->hasImage() ? '[img]' : ''
            ));
        }

        WP_CLI::success(sprintf('%d item(s) cached for "%s".', count($items), $feed->slug));
    }

    /**
     * List all feeds and their cache status.
     */
    public function status(): void
    {
        foreach ($this->feeds->all() as $feed) {
            $fetchedAt = $this->cache->fetchedAt($feed);

            WP_CLI::log(sprintf(
                '%s (%s, provider=%s): fetched %s%s',
                $feed->slug,
                $feed->name,
                $feed->providerId,
                $fetchedAt > 0 ? human_time_diff($fetchedAt) . ' ago' : 'never',
                $this->cache->lastError($feed) !== null ? ' — ERROR: ' . $this->cache->lastError($feed) : ''
            ));
        }
    }
}
