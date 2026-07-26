<?php

declare(strict_types=1);

namespace FreshetFeeds\Cache;

use FreshetFeeds\Feed\Feed;
use FreshetFeeds\Item\ItemCollection;

/**
 * Cached items live in post meta on the feed post — durable (unlike transients,
 * which object caches may evict and would break stale-while-revalidate) and
 * deleted together with the feed.
 */
final class ItemCache
{
    private const META_ITEMS = '_freshet_feeds_items';
    private const META_FETCHED_AT = '_freshet_feeds_fetched_at';
    private const META_LAST_ERROR = '_freshet_feeds_last_error';
    private const META_FAIL_COUNT = '_freshet_feeds_fail_count';

    public function get(Feed $feed): ?ItemCollection
    {
        $json = get_post_meta($feed->id, self::META_ITEMS, true);

        if (!is_string($json) || $json === '') {
            return null;
        }

        $data = json_decode($json, true);

        return is_array($data) ? ItemCollection::fromArray($data) : null;
    }

    public function store(Feed $feed, ItemCollection $items): void
    {
        update_post_meta($feed->id, self::META_ITEMS, wp_json_encode($items->toArray()));
        update_post_meta($feed->id, self::META_FETCHED_AT, time());
        delete_post_meta($feed->id, self::META_LAST_ERROR);
        delete_post_meta($feed->id, self::META_FAIL_COUNT);
    }

    public function fetchedAt(Feed $feed): int
    {
        return (int) get_post_meta($feed->id, self::META_FETCHED_AT, true);
    }

    public function isFresh(Feed $feed): bool
    {
        $fetchedAt = $this->fetchedAt($feed);

        return $fetchedAt > 0 && (time() - $fetchedAt) < $feed->ttl;
    }

    /** Failures never clobber stale items — they only mark the feed errored. */
    public function recordError(Feed $feed, string $message): void
    {
        update_post_meta($feed->id, self::META_LAST_ERROR, $message);
        update_post_meta($feed->id, self::META_FAIL_COUNT, $this->failCount($feed) + 1);
    }

    public function lastError(Feed $feed): ?string
    {
        $error = get_post_meta($feed->id, self::META_LAST_ERROR, true);

        return is_string($error) && $error !== '' ? $error : null;
    }

    public function failCount(Feed $feed): int
    {
        return (int) get_post_meta($feed->id, self::META_FAIL_COUNT, true);
    }

    /**
     * Whether cron should attempt a refresh: past TTL, extended by exponential
     * backoff after consecutive failures (capped at 6 hours).
     */
    public function isDue(Feed $feed): bool
    {
        $fetchedAt = $this->fetchedAt($feed);

        if ($fetchedAt === 0) {
            return true;
        }

        $backoff = min(6 * HOUR_IN_SECONDS, $feed->ttl * (2 ** $this->failCount($feed)));

        return (time() - $fetchedAt) >= $backoff;
    }
}
