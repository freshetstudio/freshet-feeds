<?php

declare(strict_types=1);

namespace FreshetFeeds\Tests\Unit;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeZone;
use FreshetFeeds\Item\Item;
use FreshetFeeds\Item\ItemAuthor;
use FreshetFeeds\Item\ItemCollection;
use FreshetFeeds\Item\ItemImage;

final class ItemTest extends TestCase
{
    private function item(array $overrides = []): Item
    {
        return new Item(
            id: $overrides['id'] ?? 'urn:li:share:1',
            provider: 'linkedin',
            url: 'https://example.com/post/1',
            date: new DateTimeImmutable('2026-07-01 10:00:00', new DateTimeZone('UTC')),
            content: $overrides['content'] ?? 'Hello world from the feed',
            title: $overrides['title'] ?? null,
            image: $overrides['image'] ?? null,
            author: $overrides['author'] ?? null,
            raw: ['original' => true],
        );
    }

    public function testTitleFallback(): void
    {
        $this->assertNull($this->item()->title());
        $this->assertSame('Fallback', $this->item()->title('Fallback'));
        $this->assertSame('Real', $this->item(['title' => 'Real'])->title('Fallback'));
    }

    public function testExcerptUsesWpTrimWords(): void
    {
        Functions\expect('wp_trim_words')
            ->once()
            ->with('Hello world from the feed', 3)
            ->andReturn('Hello world from…');

        $this->assertSame('Hello world from…', $this->item()->excerpt(3));
    }

    public function testImagePrefersLocalUrl(): void
    {
        $image = new ItemImage('https://remote.example/img.jpg', 'https://site.test/wp-content/uploads/freshet-feeds/1/abc.jpg');
        $item = $this->item(['image' => $image]);

        $this->assertTrue($item->hasImage());
        $this->assertSame('https://site.test/wp-content/uploads/freshet-feeds/1/abc.jpg', $item->image());
    }

    public function testImageFallsBackToRemoteUrl(): void
    {
        $item = $this->item(['image' => new ItemImage('https://remote.example/img.jpg')]);

        $this->assertSame('https://remote.example/img.jpg', $item->image());
    }

    public function testImageTagEscapesAndMergesAttrs(): void
    {
        Functions\when('esc_url')->returnArg();
        Functions\when('esc_attr')->alias(static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES));

        $item = $this->item([
            'image' => new ItemImage('https://remote.example/img.jpg', null, 'An "alt" text', 1200, 675),
        ]);

        $tag = $item->imageTag(['class' => 'card__img']);

        $this->assertStringContainsString('src="https://remote.example/img.jpg"', $tag);
        $this->assertStringContainsString('alt="An &quot;alt&quot; text"', $tag);
        $this->assertStringContainsString('class="card__img"', $tag);
        $this->assertStringContainsString('width="1200"', $tag);
        $this->assertStringContainsString('loading="lazy"', $tag);
    }

    public function testImageTagEmptyWithoutImage(): void
    {
        $this->assertSame('', $this->item()->imageTag());
    }

    public function testImageTagDropsHostileAttributeNames(): void
    {
        Functions\when('esc_url')->returnArg();
        Functions\when('esc_attr')->alias(static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES));

        $item = $this->item(['image' => new ItemImage('https://remote.example/img.jpg')]);

        $tag = $item->imageTag(['x" onerror="alert(1)' => 'y', 'data-ok' => 'kept']);

        $this->assertStringNotContainsString('onerror', $tag, 'Hostile attribute NAME must be dropped, not escaped');
        $this->assertStringContainsString('data-ok="kept"', $tag);
    }

    public function testToArrayFromArrayRoundTrip(): void
    {
        $original = $this->item([
            'title' => 'A title',
            'image' => new ItemImage('https://r.example/i.jpg', 'https://l.example/i.jpg', 'alt', 100, 50),
            'author' => new ItemAuthor('Acme', 'https://linkedin.com/company/acme'),
        ]);

        $restored = Item::fromArray($original->toArray());

        $this->assertEquals($original->toArray(), $restored->toArray());
        $this->assertSame($original->id, $restored->id);
        $this->assertSame($original->date->getTimestamp(), $restored->date->getTimestamp());
        $this->assertSame('Acme', $restored->author()?->name);
        $this->assertSame('alt', $restored->image?->alt);
        $this->assertSame(['original' => true], $restored->raw());
    }

    public function testCollectionTakeCountAndSerialization(): void
    {
        $collection = new ItemCollection([
            $this->item(['id' => 'a']),
            $this->item(['id' => 'b']),
            $this->item(['id' => 'c']),
        ]);

        $this->assertCount(3, $collection);
        $this->assertFalse($collection->isEmpty());
        $this->assertCount(2, $collection->take(2));
        $this->assertCount(3, $collection->take(99));

        $restored = ItemCollection::fromArray($collection->toArray());
        $this->assertSame(
            ['a', 'b', 'c'],
            array_map(static fn (Item $i): string => $i->id, $restored->all())
        );
    }
}
