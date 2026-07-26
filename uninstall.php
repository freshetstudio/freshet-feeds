<?php
/**
 * Uninstall cleanup — only removes data when the site owner opted in
 * via the `freshet_feeds_delete_data_on_uninstall` option.
 */

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// OAuth secrets, license key, and cron events are ALWAYS removed on uninstall —
// orphaned tokens are a liability regardless of the content-removal preference.
$freshet_feeds_connections = get_option('freshet_feeds_connections', []);

if (is_array($freshet_feeds_connections)) {
    foreach ($freshet_feeds_connections as $freshet_feeds_connection) {
        if (is_array($freshet_feeds_connection) && isset($freshet_feeds_connection['id'])) {
            delete_option('freshet_feeds_tokens_' . sanitize_key((string) $freshet_feeds_connection['id']));
        }
    }
}

delete_option('freshet_feeds_connections');
delete_option('freshet_feeds_license_key');
delete_option('freshet_feeds_license_last_ok');
wp_unschedule_hook('freshet_feeds_refresh');
wp_unschedule_hook('freshet_feeds_refresh_feed');

// Content (feeds + cached items + localized images) only goes when opted in.
if (!get_option('freshet_feeds_delete_data_on_uninstall')) {
    return;
}

delete_option('freshet_feeds_delete_data_on_uninstall');

// Feed posts + their meta (config, cached items).
$freshet_feeds_posts = get_posts([
    'post_type' => 'freshet_feeds_feed',
    'post_status' => 'any',
    'numberposts' => -1,
    'fields' => 'ids',
]);

foreach ($freshet_feeds_posts as $freshet_feeds_post_id) {
    wp_delete_post($freshet_feeds_post_id, true);
}

// Localized images: uploads/freshet-feeds/.
$freshet_feeds_upload = wp_upload_dir();
$freshet_feeds_dir = trailingslashit($freshet_feeds_upload['basedir']) . 'freshet-feeds';

if (is_dir($freshet_feeds_dir)) {
    foreach (glob($freshet_feeds_dir . '/*/*') ?: [] as $freshet_feeds_file) {
        @unlink($freshet_feeds_file); // phpcs:ignore WordPress.WP.AlternativeFunctions -- uninstall cleanup of our own uploads/freshet-feeds dir.
    }
    foreach (glob($freshet_feeds_dir . '/*', GLOB_ONLYDIR) ?: [] as $freshet_feeds_subdir) {
        @rmdir($freshet_feeds_subdir); // phpcs:ignore WordPress.WP.AlternativeFunctions
    }
    @rmdir($freshet_feeds_dir); // phpcs:ignore WordPress.WP.AlternativeFunctions
}
