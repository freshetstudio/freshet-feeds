<?php

declare(strict_types=1);

namespace FreshetFeeds\Tests\Unit;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeZone;
use FreshetFeeds\Cache\ItemCache;
use FreshetFeeds\Feed\Feed;
use FreshetFeeds\Item\Item;
use FreshetFeeds\Item\ItemCollection;

final class ItemCacheTest extends TestCase
{
    /** @var array<int, array<string, mixed>> Simulated post meta store. */
    private array $meta = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->meta = [];

        Functions\when('get_post_meta')->alias(
            fn (int $id, string $key, bool $single): mixed => $this->meta[$id][$key] ?? ''
        );
        Functions\when('update_post_meta')->alias(
            function (int $id, string $key, mixed $value): bool {
                $this->meta[$id][$key] = $value;

                return true;
            }
        );
        Functions\when('delete_post_meta')->alias(
            function (int $id, string $key): bool {
                unset($this->meta[$id][$key]);

                return true;
            }
        );
        Functions\when('wp_json_encode')->alias(static fn (mixed $v): string|false => json_encode($v));
    }

    private function feed(int $ttl = 3600): Feed
    {
        return new Feed(1, 'Test', 'test', 'mock', ttl: $ttl);
    }

    private function items(): ItemCollection
    {
        return new ItemCollection([
            new Item('a', 'mock', 'https://x.test/a', new DateTimeImmutable('now', new DateTimeZone('UTC'))),
        ]);
    }

    public function testStoreAndGetRoundTrip(): void
    {
        $cache = new ItemCache();
        $feed = $this->feed();

        $this->assertNull($cache->get($feed));

        $cache->store($feed, $this->items());

        $cached = $cache->get($feed);
        $this->assertNotNull($cached);
        $this->assertCount(1, $cached);
        $this->assertSame('a', $cached->all()[0]->id);
        $this->assertTrue($cache->isFresh($feed));
    }

    public function testStoreClearsErrorState(): void
    {
        $cache = new ItemCache();
        $feed = $this->feed();

        $cache->recordError($feed, 'boom');
        $cache->recordError($feed, 'boom again');
        $this->assertSame('boom again', $cache->lastError($feed));
        $this->assertSame(2, $cache->failCount($feed));

        $cache->store($feed, $this->items());

        $this->assertNull($cache->lastError($feed));
        $this->assertSame(0, $cache->failCount($feed));
    }

    public function testErrorDoesNotClobberItems(): void
    {
        $cache = new ItemCache();
        $feed = $this->feed();

        $cache->store($feed, $this->items());
        $cache->recordError($feed, 'transient outage');

        $this->assertNotNull($cache->get($feed), 'Stale items survive failures');
        $this->assertSame('transient outage', $cache->lastError($feed));
    }

    public function testIsDueRespectsBackoff(): void
    {
        $cache = new ItemCache();
        $feed = $this->feed(ttl: 3600);

        // Never fetched: due immediately.
        $this->assertTrue($cache->isDue($feed));

        $cache->store($feed, $this->items());
        $this->assertFalse($cache->isDue($feed), 'Fresh feed is not due');

        // Fetched 2h ago with 2 failures: backoff = ttl * 4 = 4h → not due.
        $this->meta[1]['_freshet_feeds_fetched_at'] = time() - 2 * 3600;
        $this->meta[1]['_freshet_feeds_fail_count'] = 2;
        $this->assertFalse($cache->isDue($feed));

        // Fetched 5h ago with 2 failures → due again.
        $this->meta[1]['_freshet_feeds_fetched_at'] = time() - 5 * 3600;
        $this->assertTrue($cache->isDue($feed));
    }
}
