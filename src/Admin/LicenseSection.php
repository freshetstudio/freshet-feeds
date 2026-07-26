<?php

declare(strict_types=1);

namespace FreshetFeeds\Admin;

use FreshetFeeds\License\LicenseClient;
use FreshetFeeds\License\LicenseInterface;
use FreshetFeeds\License\RemoteLicense;

/**
 * License block on the Feeds admin page: enter key → activate; deactivate;
 * status display. Server-side gating stays in FeedRepository — this is UI.
 */
final class LicenseSection
{
    public function __construct(
        private readonly LicenseClient $client,
        private readonly LicenseInterface $license,
    ) {
    }

    public function hooks(): void
    {
        add_action('admin_post_freshet_feeds_activate_license', [$this, 'activate']);
        add_action('admin_post_freshet_feeds_deactivate_license', [$this, 'deactivate']);
        add_action('admin_post_freshet_feeds_save_data_settings', [$this, 'saveDataSettings']);
    }

    public function saveDataSettings(): void
    {
        $this->authorize('freshet_feeds_save_data_settings');

        update_option('freshet_feeds_delete_data_on_uninstall', isset($_POST['delete_data']) ? 1 : 0, false);

        $this->back('saved');
    }

    public function activate(): void
    {
        $this->authorize('freshet_feeds_activate_license');

        $key = sanitize_text_field(wp_unslash($_POST['license_key'] ?? ''));

        if ($key === '') {
            $this->back('error', __('Enter a license key.', 'freshet-feeds'));
        }

        $response = $this->client->activate($key, home_url());

        if (!($response['success'] ?? false)) {
            $this->back('error', (string) ($response['error'] ?? __('Activation failed.', 'freshet-feeds')));
        }

        update_option(RemoteLicense::OPTION_KEY, $key, false);
        RemoteLicense::bustCache();

        $this->back('saved');
    }

    public function deactivate(): void
    {
        $this->authorize('freshet_feeds_deactivate_license');

        $key = RemoteLicense::storedKey();

        if ($key !== '') {
            // Best effort: free the seat server-side, but always clear locally.
            $this->client->deactivate($key, home_url());
        }

        delete_option(RemoteLicense::OPTION_KEY);
        RemoteLicense::bustCache();

        $this->back('deleted');
    }

    public function render(): void
    {
        $key = RemoteLicense::storedKey();
        $isPro = $this->license->isPro();

        echo '<h2>' . esc_html__('License', 'freshet-feeds') . '</h2>';

        if ($key !== '') {
            printf(
                '<p>%s <code>%s…%s</code> — %s</p>',
                esc_html__('Key:', 'freshet-feeds'),
                esc_html(substr($key, 0, 6)),
                esc_html(substr($key, -4)),
                $isPro
                    ? '<strong style="color:#00a32a;">' . esc_html__('Pro active — managed LinkedIn connection enabled', 'freshet-feeds') . '</strong>'
                    : '<strong style="color:#b32d2e;">' . esc_html__('Invalid or expired — managed LinkedIn connection disabled', 'freshet-feeds') . '</strong>'
            );

            $deactivateUrl = wp_nonce_url(
                add_query_arg(['action' => 'freshet_feeds_deactivate_license'], admin_url('admin-post.php')),
                'freshet_feeds_deactivate_license'
            );

            printf(
                '<p><a href="%s" class="button">%s</a></p>',
                esc_url($deactivateUrl),
                esc_html__('Deactivate license on this site', 'freshet-feeds')
            );

            $this->renderDataSettings();

            return;
        }

        echo '<p class="description">';
        printf(
            /* translators: %s: linked store URL */
            esc_html__('Feeds are free and unlimited. %s adds the managed LinkedIn connection — no LinkedIn developer app required.', 'freshet-feeds'),
            '<a href="https://freshet.studio" target="_blank" rel="noopener noreferrer">Freshet Feeds Pro</a>'
        );
        echo '</p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="max-width:600px;">';
        wp_nonce_field('freshet_feeds_activate_license');
        echo '<input type="hidden" name="action" value="freshet_feeds_activate_license">';
        printf(
            '<p><input type="text" name="license_key" class="regular-text" placeholder="%s" required> ',
            esc_attr__('License key', 'freshet-feeds')
        );
        printf('<button type="submit" class="button button-primary">%s</button></p>', esc_html__('Activate', 'freshet-feeds'));
        echo '</form>';

        $this->renderDataSettings();
    }

    /** Rendered by render() after the license block. */
    private function renderDataSettings(): void
    {
        echo '<hr style="margin:2em 0;">';
        echo '<h2>' . esc_html__('Data', 'freshet-feeds') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('freshet_feeds_save_data_settings');
        echo '<input type="hidden" name="action" value="freshet_feeds_save_data_settings">';
        printf(
            '<label><input type="checkbox" name="delete_data" value="1"%s> %s</label>',
            checked((bool) get_option('freshet_feeds_delete_data_on_uninstall'), true, false),
            esc_html__('Remove all feeds, cached items, and localized images when the plugin is uninstalled.', 'freshet-feeds')
        );
        echo '<p class="description">' . esc_html__('Connection secrets and license data are always removed on uninstall, regardless of this setting.', 'freshet-feeds') . '</p>';
        submit_button(__('Save', 'freshet-feeds'), 'secondary');
        echo '</form>';
    }

    private function authorize(string $nonceAction): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to manage the license.', 'freshet-feeds'));
        }

        check_admin_referer($nonceAction);
    }

    private function back(string $notice, string $message = ''): never
    {
        wp_safe_redirect(add_query_arg(array_filter([
            'page' => FeedsPage::SLUG,
            'tab' => 'license',
            'freshet_feeds_notice' => $notice,
            'freshet_feeds_message' => $message !== '' ? rawurlencode($message) : null,
        ]), admin_url('admin.php')));

        exit;
    }
}
