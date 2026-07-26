<?php

declare(strict_types=1);

namespace FreshetFeeds\Tests\Unit;

use Brain\Monkey\Functions;
use FreshetFeeds\License\LicenseClient;
use FreshetFeeds\License\RemoteLicense;

final class RemoteLicenseTest extends TestCase
{
    /** @var array<string, mixed> Simulated options/transients. */
    private array $options = [];
    private array $transients = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->options = [];
        $this->transients = [];

        Functions\when('apply_filters')->alias(static fn (string $hook, mixed $value): mixed => $value);
        Functions\when('untrailingslashit')->alias(static fn (string $s): string => rtrim($s, '/'));
        Functions\when('home_url')->justReturn('https://customer-site.com');
        Functions\when('get_option')->alias(fn (string $k, mixed $d = false): mixed => $this->options[$k] ?? $d);
        Functions\when('update_option')->alias(function (string $k, mixed $v): bool {
            $this->options[$k] = $v;

            return true;
        });
        Functions\when('get_transient')->alias(fn (string $k): mixed => $this->transients[$k] ?? false);
        Functions\when('set_transient')->alias(function (string $k, mixed $v): bool {
            $this->transients[$k] = $v;

            return true;
        });
        Functions\when('delete_transient')->alias(function (string $k): bool {
            unset($this->transients[$k]);

            return true;
        });
        Functions\when('wp_json_encode')->alias(static fn (mixed $v): string|false => json_encode($v));
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('__')->returnArg();
    }

    private function license(): RemoteLicense
    {
        return new RemoteLicense(new LicenseClient());
    }

    private function serverResponds(array $envelope): void
    {
        Functions\when('wp_remote_post')->justReturn(['body' => json_encode($envelope)]);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode($envelope));
    }

    public function testNoKeyMeansNoProxyEntitlement(): void
    {
        $license = $this->license();

        $this->assertFalse($license->isPro());
        $this->assertFalse($license->canUseProxy());
    }

    public function testValidKeyEnablesTheManagedPipeline(): void
    {
        $this->options[RemoteLicense::OPTION_KEY] = str_repeat('a', 32);
        $this->serverResponds(['success' => true, 'data' => ['valid' => true, 'status' => 'active']]);

        $license = $this->license();

        $this->assertTrue($license->isPro());
        $this->assertTrue($license->canUseProxy());
    }

    public function testInvalidKeyStaysFree(): void
    {
        $this->options[RemoteLicense::OPTION_KEY] = str_repeat('b', 32);
        $this->serverResponds(['success' => true, 'data' => ['valid' => false, 'status' => 'revoked']]);

        $this->assertFalse($this->license()->isPro());
    }

    public function testValidationResultIsCached(): void
    {
        $key = str_repeat('c', 32);
        $this->options[RemoteLicense::OPTION_KEY] = $key;
        $this->transients['freshet_feeds_license_status'] = ['key' => $key, 'valid' => true];

        Functions\expect('wp_remote_post')->never();

        $this->assertTrue($this->license()->isPro());
    }

    public function testCacheForDifferentKeyIsIgnored(): void
    {
        $this->options[RemoteLicense::OPTION_KEY] = str_repeat('d', 32);
        $this->transients['freshet_feeds_license_status'] = ['key' => 'old-key', 'valid' => true];
        $this->serverResponds(['success' => true, 'data' => ['valid' => false]]);

        $this->assertFalse($this->license()->isPro());
    }

    private function serverUnreachable(): void
    {
        $error = \Mockery::mock('WP_Error');
        $error->shouldReceive('get_error_message')->andReturn('timeout');
        Functions\when('wp_remote_post')->justReturn($error);
        Functions\when('is_wp_error')->alias(static fn (mixed $v): bool => $v === $error);
    }

    public function testServerUnreachableFailsOpenWithinGrace(): void
    {
        $this->options[RemoteLicense::OPTION_KEY] = str_repeat('e', 32);
        // Last successful validation: yesterday — inside the 7-day grace.
        $this->options['freshet_feeds_license_last_ok'] = time() - DAY_IN_SECONDS;
        $this->serverUnreachable();

        $license = $this->license();

        $this->assertTrue($license->isPro(), 'A license-server outage must not downgrade a paying site');
        $this->assertTrue((bool) $this->transients['freshet_feeds_license_status']['valid'], 'Fail-open result is cached for one interval');
    }

    public function testFailOpenExpiresAfterGraceWindow(): void
    {
        $this->options[RemoteLicense::OPTION_KEY] = str_repeat('f', 32);
        // Last successful validation: 8 days ago — outside the 7-day grace.
        $this->options['freshet_feeds_license_last_ok'] = time() - 8 * DAY_IN_SECONDS;
        $this->serverUnreachable();

        $this->assertFalse($this->license()->isPro(), 'Blocking the license server must not be a permanent Pro unlock');
    }

    public function testNeverValidatedKeyDoesNotFailOpen(): void
    {
        $this->options[RemoteLicense::OPTION_KEY] = str_repeat('9', 32);
        $this->serverUnreachable();

        $this->assertFalse($this->license()->isPro(), 'A key that never validated gets no benefit of the doubt');
    }

    public function testSuccessfulValidationRecordsLastOk(): void
    {
        $this->options[RemoteLicense::OPTION_KEY] = str_repeat('a', 32);
        $this->serverResponds(['success' => true, 'data' => ['valid' => true]]);

        Functions\when('update_option')->alias(function (string $k, mixed $v): bool {
            $this->options[$k] = $v;

            return true;
        });

        $this->assertTrue($this->license()->isPro());
        $this->assertGreaterThan(0, (int) ($this->options['freshet_feeds_license_last_ok'] ?? 0));
    }
}
