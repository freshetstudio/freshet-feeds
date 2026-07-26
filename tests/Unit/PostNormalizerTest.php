<?php

declare(strict_types=1);

namespace FreshetFeeds\Tests\Unit;

use FreshetFeeds\Item\ItemAuthor;
use FreshetFeeds\Provider\LinkedIn\PostNormalizer;

final class PostNormalizerTest extends TestCase
{
    private PostNormalizer $normalizer;

    /** @var array<string, mixed> */
    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new PostNormalizer();
        $this->fixture = json_decode(
            (string) file_get_contents(FRESHET_FEEDS_FIXTURES_DIR . '/linkedin-posts.json'),
            true
        );
    }

    public function testNormalizesMediaPostFromFixture(): void
    {
        $post = $this->fixture['elements'][0];
        $item = $this->normalizer->normalize($post, $this->fixture['_images']);

        $this->assertSame('urn:li:share:7208881234567890001', $item->id);
        $this->assertSame('linkedin', $item->provider);
        $this->assertSame(
            'https://www.linkedin.com/feed/update/urn%3Ali%3Ashare%3A7208881234567890001',
            $item->url
        );
        $this->assertNull($item->title, 'Plain posts have no title');
        $this->assertStringContainsString('#SpecialtyCoffee', $item->content);
        $this->assertStringContainsString('#Espresso', $item->content);
        $this->assertStringNotContainsString('{hashtag', $item->content);
        $this->assertSame(1751364000, $item->date->getTimestamp());
        $this->assertNotNull($item->image);
        $this->assertSame('https://picsum.photos/seed/freshet-feeds-1/1200/675', $item->image->remoteUrl);
        $this->assertSame('Bags of coffee on a roastery workbench at dawn', $item->image->alt);
        $this->assertSame(1200, $item->image->width);
        $this->assertSame($post, $item->raw, 'Raw payload preserved as escape hatch');
    }

    public function testArticlePostUsesArticleTitleAndThumbnail(): void
    {
        $post = $this->fixture['elements'][1];
        $item = $this->normalizer->normalize($post, $this->fixture['_images']);

        $this->assertSame('The Road to Carbon-Neutral Roasting', $item->title);
        $this->assertStringContainsString('Brew Weekly', $item->content);
        $this->assertStringNotContainsString('@[', $item->content, 'Mentions unwrapped to plain names');
        $this->assertNotNull($item->image);
        $this->assertSame('https://picsum.photos/seed/freshet-feeds-2/1200/627', $item->image->remoteUrl);
    }

    public function testMultiImagePostUsesFirstImageAndUnescapesCommentary(): void
    {
        $post = $this->fixture['elements'][2];
        $item = $this->normalizer->normalize($post, $this->fixture['_images']);

        $this->assertNotNull($item->image);
        $this->assertSame('https://picsum.photos/seed/freshet-feeds-3/1200/675', $item->image->remoteUrl);
        $this->assertStringContainsString('(part 3)', $item->content, 'Escaped parens unescaped');
        $this->assertStringContainsString('"roast is a craft"', $item->content);
    }

    public function testTextOnlyPostHasNoImageAndNoTitle(): void
    {
        $post = $this->fixture['elements'][3];
        $item = $this->normalizer->normalize($post, $this->fixture['_images']);

        $this->assertNull($item->image);
        $this->assertNull($item->title);
        $this->assertFalse($item->hasImage());
    }

    public function testUnresolvedImageUrnYieldsNoImage(): void
    {
        // Element 4 references an URN missing from _images (simulates a failed batch resolve).
        $post = $this->fixture['elements'][4];
        $item = $this->normalizer->normalize($post, []);

        $this->assertNull($item->image);
    }

    public function testOrganizationBecomesAuthor(): void
    {
        $org = new ItemAuthor('Acme', 'https://www.linkedin.com/company/acme');
        $item = $this->normalizer->normalize($this->fixture['elements'][0], [], $org);

        $this->assertSame($org, $item->author());
    }

    public function testCreatedAtFallsBackWhenPublishedAtMissing(): void
    {
        $post = $this->fixture['elements'][0];
        unset($post['publishedAt']);

        $item = $this->normalizer->normalize($post);

        $this->assertSame(1751364000, $item->date->getTimestamp());
    }

    public function testPlainCommentaryEdgeCases(): void
    {
        $this->assertSame('#Tag text', $this->normalizer->plainCommentary('{hashtag|\\#|Tag} text'));
        $this->assertSame('#Tag', $this->normalizer->plainCommentary('{hashtag|#|Tag}'));
        $this->assertSame('Acme rocks', $this->normalizer->plainCommentary('@[Acme](urn:li:organization:1) rocks'));
        $this->assertSame('a|b (c) [d]', $this->normalizer->plainCommentary('a\\|b \\(c\\) \\[d\\]'));
        $this->assertSame('', $this->normalizer->plainCommentary('  '));
    }
}
