<?php

declare(strict_types=1);

namespace FreshetFeeds\Admin;

/**
 * The Settings tab on the Feeds screen: site-wide preferences that are not
 * about any one feed. For now that is the Data section — whether uninstall
 * removes the feeds, their cached items and the localized images
 * (`freshet_feeds_delete_data_on_uninstall`, honoured by uninstall.php).
 *
 * Owns its tab through the screen's plain hooks (`freshet_feeds_tabs`,
 * `freshet_feeds_render_tab`) and ships in every build.
 */
final class SettingsSection
{
    public const TAB = 'settings';

    public function hooks(): void
    {
        add_action('admin_post_freshet_feeds_save_data_settings', [$this, 'saveDataSettings']);
        add_filter('freshet_feeds_tabs', [$this, 'addTab']);
        add_action('freshet_feeds_render_tab', [$this, 'renderTab']);
    }

    /**
     * @param array<string, string> $tabs slug => label
     * @return array<string, string>
     */
    public function addTab(array $tabs): array
    {
        $tabs[self::TAB] = __('Settings', 'freshet-feeds');

        return $tabs;
    }

    public function renderTab(string $tab): void
    {
        if ($tab === self::TAB) {
            $this->render();
        }
    }

    public function saveDataSettings(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to manage settings.', 'freshet-feeds'));
        }

        check_admin_referer('freshet_feeds_save_data_settings');

        update_option('freshet_feeds_delete_data_on_uninstall', isset($_POST['delete_data']) ? 1 : 0, false);

        $this->back('saved');
    }

    public function render(): void
    {
        echo '<h2>' . esc_html__('Data', 'freshet-feeds') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('freshet_feeds_save_data_settings');
        echo '<input type="hidden" name="action" value="freshet_feeds_save_data_settings">';
        printf(
            '<label><input type="checkbox" name="delete_data" value="1"%s> %s</label>',
            checked((bool) get_option('freshet_feeds_delete_data_on_uninstall'), true, false),
            esc_html__('Remove all feeds, cached items, and localized images when the plugin is uninstalled.', 'freshet-feeds')
        );
        echo '<p class="description">' . esc_html__('Connection secrets and scheduled refreshes are always removed on uninstall, regardless of this setting.', 'freshet-feeds') . '</p>';
        submit_button(__('Save', 'freshet-feeds'), 'secondary');
        echo '</form>';
    }

    private function back(string $notice): never
    {
        wp_safe_redirect(add_query_arg([
            'page' => FeedsPage::SLUG,
            'tab' => self::TAB,
            'freshet_feeds_notice' => $notice,
        ], admin_url('admin.php')));

        exit;
    }
}
