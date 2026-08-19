<?php

declare(strict_types=1);

namespace FreshetFeeds\License;

interface LicenseInterface
{
    public function isPro(): bool;

    /**
     * Whether this site is entitled to the managed source pipeline (the vendor
     * proxy). Distinct from isPro(): the wordpress.org build is "pro" (nothing
     * locked) yet never proxy-entitled.
     */
    public function canUseProxy(): bool;
}
