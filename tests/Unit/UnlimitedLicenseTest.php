<?php

declare(strict_types=1);

namespace FreshetFeeds\Tests\Unit;

use FreshetFeeds\License\UnlimitedLicense;

final class UnlimitedLicenseTest extends TestCase
{
    public function testDirectoryBuildIsProButNeverProxyEntitled(): void
    {
        $license = new UnlimitedLicense();

        $this->assertTrue($license->isPro(), 'wp.org build: nothing is locked');
        $this->assertFalse(
            $license->canUseProxy(),
            'The managed pipeline must never leak into the directory build — it runs on the vendor LinkedIn app quota'
        );
    }
}
