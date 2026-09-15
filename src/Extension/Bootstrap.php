<?php

declare(strict_types=1);

namespace FreshetFeeds\Extension;

use FreshetFeeds\Admin\LicenseSection;
use FreshetFeeds\License\LicenseClient;
use FreshetFeeds\License\LicenseInterface;
use FreshetFeeds\License\RemoteLicense;
use FreshetFeeds\License\UpdateChecker;

/**
 * The paid tier, booted as one unit: the license client, the license, the
 * License tab and the tier pill on the Feeds screen, and update delivery
 * from the license server. Feeds are unlimited in every build; what a
 * validating key buys here is the managed source pipeline (canUseProxy) and
 * direct support.
 *
 * This file and everything it names are stripped from the wordpress.org build
 * (bin/release.conf). Plugin asks for this class once; where the file is
 * absent the autoloader answers nothing, and the plugin is whole as it stands
 * — every hook this class attaches to is a plain extension point the screen
 * offers to anyone, and nothing outside this namespace and the files it
 * names reads a tier.
 *
 * Attaches to: `freshet_feeds_tabs`, `freshet_feeds_render_tab` and
 * `freshet_feeds_header_meta` (declared by Admin\FeedsPage), and
 * `admin_enqueue_scripts` at priority 20 for the pill's styles. Offers:
 * `freshet_feeds_license` (the license implementation) and, through
 * UpdateChecker, `freshet_feeds_direct_updates`.
 */
final class Bootstrap
{
    public static function boot(): void
    {
        $client = new LicenseClient();

        /**
         * Filter the active license implementation.
         *
         * @param LicenseInterface $license
         */
        $license = apply_filters('freshet_feeds_license', new RemoteLicense($client));

        (new LicenseSection($client, $license))->hooks();

        // Update delivery from the license server is opt-in via constant or
        // filter — UpdateChecker::hooks() attaches nothing unless it is on.
        (new UpdateChecker($client))->hooks();
    }

    /**
     * The license data, at uninstall. Called from uninstall.php, where no
     * autoloader is registered — so the names are written out here rather
     * than read off RemoteLicense, and must stay the same as its constants.
     */
    public static function uninstall(): void
    {
        delete_option('freshet_feeds_license_key');
        delete_option('freshet_feeds_license_last_ok');
        delete_transient('freshet_feeds_license_status');
    }
}
