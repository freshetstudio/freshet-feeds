<?php

declare(strict_types=1);

namespace FreshetFeeds\License;

interface LicenseInterface
{
    public function isPro(): bool;

    /**
     * Whether this site may route LinkedIn fetches through the vendor proxy —
     * the managed pipeline that spares customers a LinkedIn developer app.
     * Distinct from isPro(): the wordpress.org build is "pro" (nothing
     * locked) yet never proxy-entitled.
     */
    public function canUseProxy(): bool;
}
