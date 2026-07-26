<?php
/**
 * Plugin Name:       Freshet Feeds
 * Plugin URI:        https://freshet.studio/docs
 * Description:       Developer-first external feeds (LinkedIn first) with a shared item model and theme-overridable templates.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.2
 * Author:            Freshet Studio
 * Author URI:        https://freshet.studio
 * License:           GPL-2.0-or-later
 * Text Domain:       freshet-feeds
 * Domain Path:       /languages
 */

declare(strict_types=0); // Header file must load on old PHP to show the guard notice.

if (!defined('ABSPATH')) {
    exit;
}

define('FRESHET_FEEDS_VERSION', '1.0.0');
define('FRESHET_FEEDS_FILE', __FILE__);
define('FRESHET_FEEDS_DIR', plugin_dir_path(__FILE__));
define('FRESHET_FEEDS_URL', plugin_dir_url(__FILE__));

if (version_compare(PHP_VERSION, '8.2', '<')) {
    add_action('admin_notices', static function (): void {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html(sprintf(
                /* translators: %s: current PHP version */
                __('Freshet Feeds requires PHP 8.2 or newer. This site runs PHP %s — the plugin is inactive.', 'freshet-feeds'),
                PHP_VERSION
            ))
        );
    });

    return;
}

if (!is_readable(FRESHET_FEEDS_DIR . 'vendor/autoload.php')) {
    add_action('admin_notices', static function (): void {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__('Freshet Feeds is missing its Composer autoloader. Run "composer install" in the plugin directory.', 'freshet-feeds')
        );
    });

    return;
}

require FRESHET_FEEDS_DIR . 'vendor/autoload.php';
require FRESHET_FEEDS_DIR . 'includes/functions.php';

register_deactivation_hook(__FILE__, ['FreshetFeeds\Fetch\Cron', 'unschedule']);

FreshetFeeds\Plugin::boot();
