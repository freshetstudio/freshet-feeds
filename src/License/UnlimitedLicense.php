<?php

declare(strict_types=1);

namespace FreshetFeeds\License;

/**
 * The wordpress.org build: fully functional, no license checks — per
 * directory Guideline 5 (no locked features). Used automatically when the
 * remote-license stack is not present (it is stripped from the directory
 * build; see bin/build-release.sh).
 */
final class UnlimitedLicense implements LicenseInterface
{
    public function isPro(): bool
    {
        return true;
    }

    /**
     * Never. The proxy is a paid service running on the vendor's LinkedIn app
     * quota — isPro() being true here (nothing is locked) must not leak
     * proxy access into the directory build.
     */
    public function canUseProxy(): bool
    {
        return false;
    }
}
