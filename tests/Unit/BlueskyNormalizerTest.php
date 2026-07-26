<?php

declare(strict_types=1);

namespace FreshetFeeds\Tests\Unit;

use FreshetFeeds\Provider\Bluesky\BlueskyNormalizer;

final class BlueskyNormalizerTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $fixture;
    private BlueskyNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new BlueskyNormalizer();
        $this->fixture = json_decode(
            (string) file_get_contents(FRESHET_FEEDS_FIXTURES_DIR . '/bluesky-feed.json'),
            true
        );
    }

    public function testNormalizesOwnPostsAndSkipsReposts(): void
    {
        $items = $this->normalizer->normalize($this->fixture);

        $this->assertCount(3, $items, 'The repost entry (reason set) is filtered out');

        foreach ($items as $item) {
            $this->assertSame('bluesky', $item->provider);
            $this->assertSame('Copperline Coffee Roasters', $item->author()?->name);
        }
    }

    public function testImagePostMapsFullsizeWithDimensions(): void
    {
        $first = $this->normalizer->normalize($this->fixture)[0];

        $this->assertSame(
            'https://bsky.app/profile/acme.example.com/post/3kfixture001',
            $first->url
        );
        $this->assertStringContainsString('single-origin espresso line', $first->content);
        $this->assertNull($first->title, 'Bluesky posts have no titles');
        $this->assertSame(
            'https://cdn.bsky.app/img/feed_fullsize/plain/did:plc:acme123/bafyimg001@jpeg',
            $first->image?->remoteUrl
        );
        $this->assertSame('Bags of coffee on a roastery workbench at dawn', $first->image?->alt);
        $this->assertSame(1200, $first->image?->width);
        $this->assertSame('2026-07-01', $first->datetime()->format('Y-m-d'));
    }

    public function testExternalLinkCardThumbUsedAsImage(): void
    {
        $linkPost = $this->normalizer->normalize($this->fixture)[1];

        $this->assertSame(
            'https://cdn.bsky.app/img/feed_thumbnail/plain/did:plc:acme123/bafylink002@jpeg',
            $linkPost->image?->remoteUrl
        );
    }

    public function testTextOnlyPostHasNoImage(): void
    {
        $textPost = $this->normalizer->normalize($this->fixture)[2];

        $this->assertNull($textPost->image);
        $this->assertFalse($textPost->hasImage());
    }

    public function testAuthorProfileUrlAndAvatar(): void
    {
        $first = $this->normalizer->normalize($this->fixture)[0];

        $this->assertSame('https://bsky.app/profile/acme.example.com', $first->author()?->url);
        $this->assertNotNull($first->author()?->imageUrl);
    }

    public function testEmptyFeedYieldsNoItems(): void
    {
        $this->assertSame([], $this->normalizer->normalize(['feed' => []]));
        $this->assertSame([], $this->normalizer->normalize([]));
    }
}
