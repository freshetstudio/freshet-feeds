<?php

declare(strict_types=1);

namespace FreshetFeeds\Admin;

use FreshetFeeds\License\LicenseClient;
use FreshetFeeds\License\LicenseInterface;
use FreshetFeeds\License\RemoteLicense;

/**
 * License block on the Feeds admin page: enter key → activate; deactivate;
 * status display. Server-side gating stays in FeedRepository — this is UI.
 *
 * It owns its tab on the screen and the tier pill in the header, through the
 * screen's hooks (`freshet_feeds_tabs`, `freshet_feeds_render_tab`,
 * `freshet_feeds_header_meta`): the tab appears wherever there is a license
 * stack to show at all, which is every build that carries this file.
 */
final class LicenseSection
{
    public const TAB = 'license';

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
        add_filter('freshet_feeds_tabs', [$this, 'addTab']);
        add_action('freshet_feeds_render_tab', [$this, 'renderTab']);
        add_action('freshet_feeds_header_meta', [$this, 'renderPill']);
        // Priority 20: after FeedsPage::enqueueAdminStyle() has registered the handle.
        add_action('admin_enqueue_scripts', [$this, 'enqueueStyle'], 20);
    }

    /**
     * @param array<string, string> $tabs slug => label
     * @return array<string, string>
     */
    public function addTab(array $tabs): array
    {
        $tabs[self::TAB] = __('License', 'freshet-feeds');

        return $tabs;
    }

    public function renderTab(string $tab): void
    {
        if ($tab === self::TAB) {
            $this->render();
        }
    }

    /**
     * The tier, in the header's meta strip, from the license itself rather
     * than from which files are on disk.
     */
    public function renderPill(): void
    {
        if ($this->license->isPro()) {
            printf('<span class="frst-header__pill frst-header__pill--pro">%s</span>', esc_html__('Pro', 'freshet-feeds'));

            return;
        }

        printf('<span class="frst-header__pill frst-header__pill--free">%s</span>', esc_html__('Free', 'freshet-feeds'));
        printf(
            '<a href="https://freshet.studio" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_html__('Upgrade', 'freshet-feeds')
        );
    }

    /** The pill's styles, on exactly the screen the shared sheet is on. */
    public function enqueueStyle(): void
    {
        if (!wp_style_is('freshet-feeds-admin', 'enqueued')) {
            return;
        }

        wp_add_inline_style('freshet-feeds-admin', '
            .frst-header__pill { border-radius: 12px; padding: 3px 10px; font-weight: 600; font-size: 12px; }
            .frst-header__pill--pro { background: #edfaef; color: #00832a; }
            .frst-header__pill--free { background: #f0f0f1; color: #50575e; }
        ');
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
                    ? '<strong style="color:#00a32a;">' . esc_html__('Pro active — managed source pipeline enabled', 'freshet-feeds') . '</strong>'
                    : '<strong style="color:#b32d2e;">' . esc_html__('Invalid or expired — managed source pipeline disabled', 'freshet-feeds') . '</strong>'
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
            esc_html__('Feeds are free and unlimited. %s adds the managed source pipeline and direct support.', 'freshet-feeds'),
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
            'tab' => self::TAB,
            'freshet_feeds_notice' => $notice,
            'freshet_feeds_message' => $message !== '' ? rawurlencode($message) : null,
        ]), admin_url('admin.php')));

        exit;
    }
}
