<?php

declare(strict_types=1);

namespace FreshetFeeds\Tests\Unit;

use Brain\Monkey\Functions;
use FreshetFeeds\Feed\Feed;
use FreshetFeeds\Provider\FetchException;
use FreshetFeeds\Provider\LinkedIn\PostNormalizer;
use FreshetFeeds\Provider\MockProvider;

final class MockProviderTest extends TestCase
{
    private const FIXTURE = FRESHET_FEEDS_FIXTURES_DIR . '/linkedin-posts.json';

    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('__')->returnArg();
        Functions\when('esc_html')->returnArg();
        Functions\when('esc_html__')->returnArg();
        Functions\when('wp_get_environment_type')->justReturn('local');
    }

    private function provider(string $fixture = self::FIXTURE): MockProvider
    {
        return new MockProvider(new PostNormalizer(), $fixture);
    }

    private function feed(int $count = 10): Feed
    {
        return new Feed(1, 'Mock', 'mock', MockProvider::ID, count: $count);
    }

    public function testFetchIsBlockedInProductionEvenForAnExistingFeed(): void
    {
        Functions\when('wp_get_environment_type')->justReturn('production');

        $this->assertFalse(MockProvider::isAvailable());

        $this->expectException(FetchException::class);
        $this->expectExceptionMessageMatches('/disabled in production/');

        $this->provider()->fetch($this->feed());
    }

    /** The environment is the gate, not the admin dropdown. */
    public function testFetchIsAllowedOutsideProduction(): void
    {
        foreach (['local', 'development', 'staging'] as $environment) {
            Functions\when('wp_get_environment_type')->justReturn($environment);

            $this->assertTrue(MockProvider::isAvailable(), $environment);
            $this->assertGreaterThan(0, $this->provider()->fetch($this->feed())->count(), $environment);
        }
    }

    public function testUnreadableFixtureThrows(): void
    {
        $this->expectException(FetchException::class);

        $this->provider(FRESHET_FEEDS_FIXTURES_DIR . '/does-not-exist.json')->fetch($this->feed());
    }

    public function testFixtureHasEnoughPostsToExerciseCountsAboveFive(): void
    {
        $items = $this->provider()->fetch($this->feed(count: 8));

        $this->assertCount(8, $items, 'A count > 5 must not be silently truncated by the fixture size');
    }

    public function testTakeRespectsTheFeedCount(): void
    {
        $this->assertCount(3, $this->provider()->fetch($this->feed(count: 3)));
    }

    public function testEveryFixturePostNormalizesThroughTheLiveMapping(): void
    {
        $items = $this->provider()->fetch($this->feed(count: 50))->all();
        $payload = json_decode((string) file_get_contents(self::FIXTURE), true);

        $this->assertCount(count($payload['elements']), $items);

        foreach ($items as $item) {
            $this->assertSame('linkedin', $item->provider, 'Mock runs the same PostNormalizer as live LinkedIn');
            $this->assertNotSame('', $item->id);
            $this->assertStringNotContainsString('{hashtag', $item->content);
        }
    }

    public function testOrganizationFromFixtureBecomesTheItemAuthor(): void
    {
        $item = $this->provider()->fetch($this->feed(count: 1))->all()[0];

        $this->assertSame('Copperline Coffee Roasters', $item->author()?->name);
    }
}
