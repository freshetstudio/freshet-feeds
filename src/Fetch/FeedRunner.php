<?php

declare(strict_types=1);

namespace FreshetFeeds\Fetch;

use FreshetFeeds\Cache\ImageStore;
use FreshetFeeds\Cache\ItemCache;
use FreshetFeeds\Feed\Feed;
use FreshetFeeds\Item\ItemCollection;
use FreshetFeeds\Provider\ProviderRegistry;
use Throwable;

/**
 * The single fetch pipeline: provider → image localization → cache.
 * Every refresh path (cron, cold start, CLI, admin preview) goes through run().
 */
final class FeedRunner
{
    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly ItemCache $cache,
        private readonly ImageStore $images,
        private readonly FetchLock $lock,
    ) {
    }

    /**
     * Refresh a feed's cache. Returns the fresh items, or null when the fetch
     * was skipped (locked) or failed — the stale cache stays untouched either way.
     */
    public function run(Feed $feed, bool $force = false): ?ItemCollection
    {
        // Forced runs bypass the lock but must not release one they don't hold —
        // that would defeat stampede protection for a concurrent normal fetch.
        $acquired = !$force && $this->lock->acquire($feed);

        if (!$force && !$acquired) {
            return null;
        }

        try {
            $provider = $this->providers->get($feed->providerId);

            if ($provider === null) {
                $this->cache->recordError($feed, sprintf('Unknown provider "%s".', $feed->providerId));

                return null;
            }

            $items = $provider->fetch($feed);
            $items = $this->images->localize($feed, $items);
            $this->cache->store($feed, $items);

            /**
             * Fires after a feed's cache has been refreshed.
             *
             * @param Feed $feed
             * @param ItemCollection $items
             */
            do_action('freshet_feeds_feed_refreshed', $feed, $items);

            return $items;
        } catch (Throwable $e) {
            $this->cache->recordError($feed, $e->getMessage());

            return null;
        } finally {
            if ($acquired) {
                $this->lock->release($feed);
            }
        }
    }
}
