<?php

declare(strict_types=1);

namespace FreshetFeeds\Tests\Unit;

use Brain\Monkey\Functions;
use FreshetFeeds\Connection\ConnectionRepository;
use FreshetFeeds\Connection\TokenStore;
use FreshetFeeds\Feed\Feed;
use FreshetFeeds\License\LicenseInterface;
use FreshetFeeds\Provider\LinkedIn\LinkedInClientInterface;
use FreshetFeeds\Provider\LinkedIn\LinkedInProvider;
use FreshetFeeds\Provider\LinkedIn\PostNormalizer;

/**
 * The acceptance seam: a proxy-entitled license routes the fetch through
 * ProxyLinkedInClient (mocked at the HTTP layer with the fixture shape);
 * an unentitled license keeps the injected BYO client.
 */
final class LinkedInProviderClientSelectionTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $fixture;

    /** @var list<string> URLs the (mocked) proxy received. */
    private array $proxyUrls = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = json_decode(
            (string) file_get_contents(FRESHET_FEEDS_FIXTURES_DIR . '/linkedin-posts.json'),
            true
        );
        $this->proxyUrls = [];

        // Filters pass through their default — proxy selection comes from the license.
        Functions\when('apply_filters')->alias(static fn (string $hook, mixed $value): mixed => $value);
        Functions\when('get_option')->alias(
            static fn (string $k, mixed $d = false): mixed
                => $k === 'freshet_feeds_license_key' ? str_repeat('a', 32) : $d
        );
        Functions\when('home_url')->justReturn('https://customer-site.com');
        Functions\when('wp_json_encode')->alias(static fn (mixed $v): string|false => json_encode($v));
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->alias(static fn (mixed $r): string => (string) ($r['body'] ?? ''));
        Functions\when('esc_html')->returnArg();
        Functions\when('esc_html__')->returnArg();
    }

    private function license(bool $proxy): LicenseInterface
    {
        return new class ($proxy) implements LicenseInterface {
            public function __construct(private readonly bool $proxy)
            {
            }

            public function isPro(): bool
            {
                return true;
            }

            public function canUseProxy(): bool
            {
                return $this->proxy;
            }
        };
    }

    private function provider(LinkedInClientInterface $byoClient, LicenseInterface $license): LinkedInProvider
    {
        return new LinkedInProvider(
            $byoClient,
            new PostNormalizer(),
            new ConnectionRepository(new TokenStore()),
            $license,
        );
    }

    private function feed(): Feed
    {
        return new Feed(
            id: 1,
            name: 'Acme on LinkedIn',
            slug: 'acme-linkedin',
            providerId: 'linkedin',
            settings: ['organization_id' => '2414183', 'organization_name' => 'Acme'],
            count: 5,
        );
    }

    /** A BYO client that fails the test if the provider falls back to it. */
    private function untouchableByoClient(): LinkedInClientInterface
    {
        return new class implements LinkedInClientInterface {
            public function getOrganizationPosts(Feed $feed, string $orgUrn, int $count): array
            {
                throw new \LogicException('BYO client must not be used when the license entitles the proxy');
            }

            public function resolveImages(Feed $feed, array $imageUrns): array
            {
                throw new \LogicException('BYO client must not be used when the license entitles the proxy');
            }
        };
    }

    public function testEntitledLicenseFetchesThroughTheProxy(): void
    {
        Functions\when('wp_remote_post')->alias(function (string $url) {
            $this->proxyUrls[] = $url;

            $envelope = str_contains($url, '/linkedin/posts')
                ? ['success' => true, 'data' => ['elements' => $this->fixture['elements']]]
                : ['success' => true, 'data' => ['images' => $this->fixture['_images']]];

            return ['body' => json_encode($envelope)];
        });

        $items = $this->provider($this->untouchableByoClient(), $this->license(true))->fetch($this->feed());

        $this->assertCount(5, $items);
        $this->assertSame(
            'https://picsum.photos/seed/freshet-feeds-1/1200/675',
            $items->all()[0]->image?->remoteUrl,
            'Signed URLs from the proxy reach the normalizer unchanged'
        );
        $this->assertSame('https://api.freshet.studio/api/v1/linkedin/posts', $this->proxyUrls[0]);
        $this->assertSame('https://api.freshet.studio/api/v1/linkedin/images', $this->proxyUrls[1]);
    }

    public function testUnentitledLicenseUsesTheInjectedByoClient(): void
    {
        Functions\expect('wp_remote_post')->never();

        $byo = new class ($this->fixture) implements LinkedInClientInterface {
            /** @param array<string, mixed> $fixture */
            public function __construct(private readonly array $fixture)
            {
            }

            public function getOrganizationPosts(Feed $feed, string $orgUrn, int $count): array
            {
                return $this->fixture['elements'];
            }

            public function resolveImages(Feed $feed, array $imageUrns): array
            {
                return $this->fixture['_images'];
            }
        };

        $items = $this->provider($byo, $this->license(false))->fetch($this->feed());

        $this->assertCount(5, $items);
    }
}
