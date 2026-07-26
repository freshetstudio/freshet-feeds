<?php

declare(strict_types=1);

namespace FreshetFeeds\Admin;

use FreshetFeeds\Auth\LinkedInOAuth;
use Throwable;

/**
 * admin-post.php endpoints for the LinkedIn OAuth dance.
 */
final class OAuthController
{
    public function __construct(private readonly LinkedInOAuth $oauth)
    {
    }

    public function hooks(): void
    {
        add_action('admin_post_freshet_feeds_oauth_start', [$this, 'start']);
        add_action('admin_post_freshet_feeds_oauth_callback', [$this, 'callback']);
    }

    public function start(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to manage feed connections.', 'freshet-feeds'));
        }

        check_admin_referer('freshet_feeds_oauth_start');

        $connectionId = sanitize_key(wp_unslash($_GET['connection'] ?? ''));
        $connection = \FreshetFeeds\Plugin::instance()->connections()->find($connectionId);

        if ($connection === null) {
            wp_die(esc_html__('Unknown connection.', 'freshet-feeds'));
        }

        // Manual redirect: LinkedIn requires the redirect_uri to be double-checked,
        // and wp_safe_redirect() would strip the external host.
        wp_redirect($this->oauth->authorizeUrl($connection)); // phpcs:ignore WordPress.Security.SafeRedirect

        exit;
    }

    public function callback(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to manage feed connections.', 'freshet-feeds'));
        }

        $code = sanitize_text_field(wp_unslash($_GET['code'] ?? ''));
        $state = sanitize_text_field(wp_unslash($_GET['state'] ?? ''));
        $error = sanitize_text_field(wp_unslash($_GET['error_description'] ?? $_GET['error'] ?? ''));

        if ($error !== '' || $code === '') {
            $this->redirectWithNotice('error', $error !== '' ? $error : __('LinkedIn returned no authorization code.', 'freshet-feeds'));
        }

        try {
            $connection = $this->oauth->handleCallback($code, $state);
            $this->redirectWithNotice('connected', $connection->label);
        } catch (Throwable $e) {
            $this->redirectWithNotice('error', $e->getMessage());
        }
    }

    private function redirectWithNotice(string $type, string $message): never
    {
        wp_safe_redirect(add_query_arg([
            'page' => FeedsPage::SLUG,
            'tab' => 'connections',
            'freshet_feeds_notice' => $type,
            'freshet_feeds_message' => rawurlencode($message),
        ], admin_url('admin.php')));

        exit;
    }
}
