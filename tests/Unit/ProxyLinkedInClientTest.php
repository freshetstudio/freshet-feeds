<?php

declare(strict_types=1);

namespace FreshetFeeds\Tests\Unit;

use Brain\Monkey\Functions;
use FreshetFeeds\Feed\Feed;
use FreshetFeeds\Provider\FetchException;
use FreshetFeeds\Provider\LinkedIn\ProxyLinkedInClient;

final class ProxyLinkedInClientTest extends TestCase
{
    /** @var array<string, mixed> Simulated options. */
    private array $options = [];

    /** @var list<array{url: string, body: array<string, mixed>}> Captured proxy requests. */
    private array $requests = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->options = ['freshet_feeds_license_key' => str_repeat('a', 32)];
        $this->requests = [];

        Functions\when('get_option')->alias(fn (string $k, mixed $d = false): mixed => $this->options[$k] ?? $d);
        Functions\when('home_url')->justReturn('https://customer-site.com');
        Functions\when('wp_json_encode')->alias(static fn (mixed $v): string|false => json_encode($v));
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->alias(static fn (mixed $r): string => (string) ($r['body'] ?? ''));
        Functions\when('esc_html')->returnArg();
        Functions\when('esc_html__')->returnArg();
    }

    private function feed(): Feed
    {
        return new Feed(id: 1, name: 'Acme', slug: 'acme', providerId: 'linkedin');
    }

    private function proxyResponds(array $envelope): void
    {
        Functions\when('wp_remote_post')->alias(function (string $url, array $args) use ($envelope): array {
            $this->requests[] = ['url' => $url, 'body' => (array) json_decode((string) $args['body'], true)];

            return ['body' => json_encode($envelope)];
        });
    }

    public function testPostsRequestAuthenticatesWithLicenseKey(): void
    {
        $fixture = json_decode((string) file_get_contents(FRESHET_FEEDS_FIXTURES_DIR . '/linkedin-posts.json'), true);
        $this->proxyResponds(['success' => true, 'data' => ['elements' => $fixture['elements']]]);

        $posts = (new ProxyLinkedInClient())->getOrganizationPosts($this->feed(), 'urn:li:organization:2414183', 5);

        $this->assertSame($fixture['elements'], $posts);
        $this->assertCount(1, $this->requests);
        $this->assertSame('https://api.freshet.studio/api/v1/linkedin/posts', $this->requests[0]['url']);
        $this->assertSame(str_repeat('a', 32), $this->requests[0]['body']['key']);
        $this->assertSame('https://customer-site.com', $this->requests[0]['body']['site_url']);
        $this->assertSame('urn:li:organization:2414183', $this->requests[0]['body']['organization']);
        $this->assertSame(5, $this->requests[0]['body']['count']);
    }

    public function testCountIsClampedToLinkedInLimits(): void
    {
        $this->proxyResponds(['success' => true, 'data' => ['elements' => []]]);
        $client = new ProxyLinkedInClient();

        $client->getOrganizationPosts($this->feed(), 'urn:li:organization:1', 500);
        $client->getOrganizationPosts($this->feed(), 'urn:li:organization:1', 0);

        $this->assertSame(50, $this->requests[0]['body']['count']);
        $this->assertSame(1, $this->requests[1]['body']['count']);
    }

    public function testMissingLicenseKeyThrowsWithoutCallingTheProxy(): void
    {
        $this->options = [];
        Functions\expect('wp_remote_post')->never();

        $this->expectException(FetchException::class);

        (new ProxyLinkedInClient())->getOrganizationPosts($this->feed(), 'urn:li:organization:1', 5);
    }

    public function testErrorEnvelopeThrowsWithServerMessage(): void
    {
        $this->proxyResponds(['success' => false, 'error' => 'License expired', 'error_code' => 'invalid_license']);

        $this->expectException(FetchException::class);
        $this->expectExceptionMessage('License expired');

        (new ProxyLinkedInClient())->getOrganizationPosts($this->feed(), 'urn:li:organization:1', 5);
    }

    public function testInvalidJsonThrows(): void
    {
        Functions\when('wp_remote_post')->justReturn(['body' => 'not json']);

        $this->expectException(FetchException::class);

        (new ProxyLinkedInClient())->getOrganizationPosts($this->feed(), 'urn:li:organization:1', 5);
    }

    public function testUnreachableProxyThrows(): void
    {
        $error = \Mockery::mock('WP_Error');
        $error->shouldReceive('get_error_message')->andReturn('timeout');
        Functions\when('wp_remote_post')->justReturn($error);
        Functions\when('is_wp_error')->alias(static fn (mixed $v): bool => $v === $error);

        $this->expectException(FetchException::class);
        $this->expectExceptionMessage('timeout');

        (new ProxyLinkedInClient())->getOrganizationPosts($this->feed(), 'urn:li:organization:1', 5);
    }

    public function testMissingElementsThrows(): void
    {
        $this->proxyResponds(['success' => true, 'data' => []]);

        $this->expectException(FetchException::class);

        (new ProxyLinkedInClient())->getOrganizationPosts($this->feed(), 'urn:li:organization:1', 5);
    }

    public function testResolveImagesSkipsHttpForEmptyUrnList(): void
    {
        Functions\expect('wp_remote_post')->never();

        $this->assertSame([], (new ProxyLinkedInClient())->resolveImages($this->feed(), []));
    }

    public function testResolveImagesParsesSignedUrlMap(): void
    {
        $this->proxyResponds(['success' => true, 'data' => ['images' => [
            'urn:li:image:1' => ['url' => 'https://cdn.example/signed-1.jpg', 'width' => 1200, 'height' => 675],
            'urn:li:image:2' => ['width' => 100],
        ]]]);

        $map = (new ProxyLinkedInClient())->resolveImages($this->feed(), ['urn:li:image:1', 'urn:li:image:2']);

        $this->assertSame('https://api.freshet.studio/api/v1/linkedin/images', $this->requests[0]['url']);
        $this->assertSame(['urn:li:image:1', 'urn:li:image:2'], $this->requests[0]['body']['urns']);
        $this->assertSame(
            ['urn:li:image:1' => ['url' => 'https://cdn.example/signed-1.jpg', 'width' => 1200, 'height' => 675]],
            $map,
            'Entries without a url are dropped'
        );
    }
}
