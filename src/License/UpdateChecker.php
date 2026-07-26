<?php

declare(strict_types=1);

namespace FreshetFeeds\License;

/**
 * Injects pro plugin updates from the license server into WP's update system.
 * Only runs when a license key is stored; the manifest is cached for 6 hours
 * so wp-admin page loads never trigger a remote call. Failures are silent —
 * updates simply don't appear.
 */
final class UpdateChecker
{
    private const CACHE_KEY = 'freshet_feeds_update_manifest';
    private const CACHE_TTL = 6 * HOUR_IN_SECONDS;

    public function __construct(private readonly LicenseClient $client)
    {
    }

    public function hooks(): void
    {
        // wp.org guideline 8: directory-hosted plugins update via wordpress.org
        // only. Direct updates are opt-in for installs distributed outside the
        // directory (define FRESHET_FEEDS_DIRECT_UPDATES or use the filter).
        if (!$this->directUpdatesEnabled()) {
            return;
        }

        add_filter('pre_set_site_transient_update_plugins', [$this, 'injectUpdate']);
        add_action('upgrader_process_complete', [$this, 'flushManifest'], 10, 0);
    }

    private function directUpdatesEnabled(): bool
    {
        $default = defined('FRESHET_FEEDS_DIRECT_UPDATES') && FRESHET_FEEDS_DIRECT_UPDATES;

        /**
         * Enable update delivery from the license server instead of wordpress.org.
         * Only for installs NOT sourced from the wordpress.org directory.
         *
         * @param bool $enabled
         */
        return (bool) apply_filters('freshet_feeds_direct_updates', $default);
    }

    public function injectUpdate(mixed $transient): mixed
    {
        if (!is_object($transient) || RemoteLicense::storedKey() === '') {
            return $transient;
        }

        $manifest = $this->manifest();

        if ($manifest === null) {
            return $transient;
        }

        $basename = plugin_basename(FRESHET_FEEDS_FILE);

        if (
            isset($manifest['latest_version'], $manifest['download_url'])
            && version_compare((string) $manifest['latest_version'], FRESHET_FEEDS_VERSION, '>')
        ) {
            $transient->response ??= [];
            $transient->response[$basename] = (object) [
                'slug' => 'freshet-feeds',
                'plugin' => $basename,
                'new_version' => (string) $manifest['latest_version'],
                'package' => (string) $manifest['download_url'],
                'url' => 'https://freshet.studio',
                'requires' => $manifest['requires_wp'] ?? '',
                'requires_php' => $manifest['requires_php'] ?? '',
                'tested' => $manifest['tested'] ?? '',
            ];
        }

        return $transient;
    }

    public function flushManifest(): void
    {
        delete_transient(self::CACHE_KEY);
    }

    /** @return array<string, mixed>|null */
    private function manifest(): ?array
    {
        $cached = get_transient(self::CACHE_KEY);

        if (is_array($cached)) {
            return $cached === [] ? null : $cached;
        }

        $response = $this->client->updateCheck(FRESHET_FEEDS_VERSION, RemoteLicense::storedKey());

        $manifest = ($response['success'] ?? false) && is_array($response['data'] ?? null)
            ? $response['data']
            : [];

        // Cache failures too (as []) so a down server isn't polled per page load.
        set_transient(self::CACHE_KEY, $manifest, self::CACHE_TTL);

        return $manifest === [] ? null : $manifest;
    }
}
