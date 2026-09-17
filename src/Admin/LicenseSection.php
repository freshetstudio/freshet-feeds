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

    public function activate(): void
    {
        $this->authorize('freshet_feeds_activate_license');

        $key = $this->normalizeKey(sanitize_text_field(wp_unslash($_POST['license_key'] ?? '')));

        if ($key === '') {
            $this->back('error', __('Enter a license key.', 'freshet-feeds'));
        }

        $response = $this->client->activate($key, home_url());

        if (!($response['success'] ?? false)) {
            $this->back('error', $this->failureMessage($response));
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
    }

    /**
     * What to tell the customer when the key was not stored. The server's own
     * sentence says it best and is passed through as written; an unknown key
     * gets the one thing the server cannot know — the paste has already been
     * cleaned (normalizeKey), so re-pasting it will not change the answer.
     *
     * @param array{success?: bool, error?: string, error_code?: string} $response
     */
    private function failureMessage(array $response): string
    {
        $detail = trim((string) ($response['error'] ?? ''));

        return match ((string) ($response['error_code'] ?? '')) {
            'invalid_key' => trim(sprintf(
                /* translators: %s: the license server's own sentence about the key */
                __('%s Spaces and invisible characters were already stripped before sending, so pasting it again will not help — compare it character by character.', 'freshet-feeds'),
                $detail
            )),
            default => $detail !== '' ? $detail : __('Activation failed.', 'freshet-feeds'),
        };
    }

    /**
     * A key pasted out of an email routinely arrives wrapped in a non-breaking
     * space or a zero-width character, neither of which sanitize_text_field()
     * removes — and the server then correctly answers "unknown key" about a
     * key that was copied correctly. Drop what is invisible and change nothing
     * else: which characters a key may contain is the server's business.
     * Same as Unused Media's.
     */
    private function normalizeKey(string $key): string
    {
        $stripped = preg_replace('/[\s\x{00A0}\x{00AD}\x{180E}\x{200B}-\x{200F}\x{2060}\x{FEFF}]/u', '', $key);

        return is_string($stripped) ? $stripped : trim($key);
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
