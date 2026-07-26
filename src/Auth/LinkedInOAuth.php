<?php

declare(strict_types=1);

namespace FreshetFeeds\Auth;

use RuntimeException;
use FreshetFeeds\Connection\ConnectionRepository;
use FreshetFeeds\Connection\LinkedInConnection;

/**
 * LinkedIn OAuth 2.0 authorization-code flow for bring-your-own-app connections.
 * The redirect URI (admin-post.php?action=freshet_feeds_oauth_callback) must be
 * registered verbatim in the LinkedIn developer app.
 */
final class LinkedInOAuth
{
    private const AUTHORIZE_URL = 'https://www.linkedin.com/oauth/v2/authorization';
    private const TOKEN_URL = 'https://www.linkedin.com/oauth/v2/accessToken';
    private const SCOPES = 'r_organization_social rw_organization_admin';
    private const STATE_TRANSIENT = 'freshet_feeds_oauth_state';

    /** Per-user key so two admins connecting concurrently don't clobber each other. */
    private static function stateKey(): string
    {
        return self::STATE_TRANSIENT . '_' . get_current_user_id();
    }

    public function __construct(private readonly ConnectionRepository $connections)
    {
    }

    public static function redirectUri(): string
    {
        return admin_url('admin-post.php?action=freshet_feeds_oauth_callback');
    }

    /** Build the LinkedIn consent URL for a connection and remember the state nonce. */
    public function authorizeUrl(LinkedInConnection $connection): string
    {
        $state = wp_generate_password(24, false);

        set_transient(self::stateKey(), [
            'state' => $state,
            'connection_id' => $connection->id,
        ], 10 * MINUTE_IN_SECONDS);

        return add_query_arg([
            'response_type' => 'code',
            'client_id' => $connection->clientId,
            'redirect_uri' => rawurlencode(self::redirectUri()),
            'state' => $state,
            'scope' => rawurlencode(self::SCOPES),
        ], self::AUTHORIZE_URL);
    }

    /**
     * Handle the callback: verify state, exchange the code, persist tokens.
     *
     * @throws RuntimeException On state mismatch or token exchange failure.
     */
    public function handleCallback(string $code, string $state): LinkedInConnection
    {
        $expected = get_transient(self::stateKey());
        delete_transient(self::stateKey());

        if (!is_array($expected) || !hash_equals((string) $expected['state'], $state)) {
            throw new RuntimeException(esc_html__('OAuth state mismatch — please retry the connection.', 'freshet-feeds'));
        }

        $connection = $this->connections->find((string) $expected['connection_id']);

        if ($connection === null) {
            throw new RuntimeException(esc_html__('Unknown connection.', 'freshet-feeds'));
        }

        $secrets = $this->connections->tokens()->get($connection->id);

        $tokens = $this->requestTokens([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $connection->clientId,
            'client_secret' => (string) ($secrets['client_secret'] ?? ''),
            'redirect_uri' => self::redirectUri(),
        ]);

        return $this->storeTokens($connection, $secrets, $tokens);
    }

    /**
     * Refresh the access token (refresh_token grant; available on
     * Community Management-approved apps).
     *
     * @throws RuntimeException When refreshing fails — caller flags reauth.
     */
    public function refresh(LinkedInConnection $connection): LinkedInConnection
    {
        $secrets = $this->connections->tokens()->get($connection->id);

        if (($secrets['refresh_token'] ?? '') === '') {
            throw new RuntimeException('No refresh token stored.');
        }

        $tokens = $this->requestTokens([
            'grant_type' => 'refresh_token',
            'refresh_token' => (string) $secrets['refresh_token'],
            'client_id' => $connection->clientId,
            'client_secret' => (string) ($secrets['client_secret'] ?? ''),
        ]);

        return $this->storeTokens($connection, $secrets, $tokens);
    }

    /**
     * @param array<string, string> $body
     * @return array{access_token: string, expires_in: int, refresh_token?: string, refresh_token_expires_in?: int}
     */
    private function requestTokens(array $body): array
    {
        $response = wp_remote_post(self::TOKEN_URL, [
            'timeout' => 15,
            'body' => $body,
        ]);

        if (is_wp_error($response)) {
            throw new RuntimeException(esc_html($response->get_error_message()));
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($data) || !is_string($data['access_token'] ?? null)) {
            $error = is_array($data) ? (string) ($data['error_description'] ?? $data['error'] ?? '') : '';

            throw new RuntimeException(esc_html(sprintf(
                'LinkedIn token request failed (HTTP %d)%s',
                (int) wp_remote_retrieve_response_code($response),
                $error !== '' ? ': ' . $error : ''
            )));
        }

        return $data;
    }

    /**
     * @param array{client_secret?: string, access_token?: string, refresh_token?: string} $secrets
     * @param array{access_token: string, expires_in: int, refresh_token?: string, refresh_token_expires_in?: int} $tokens
     */
    private function storeTokens(LinkedInConnection $connection, array $secrets, array $tokens): LinkedInConnection
    {
        $this->connections->tokens()->save($connection->id, [
            'client_secret' => (string) ($secrets['client_secret'] ?? ''),
            'access_token' => $tokens['access_token'],
            // LinkedIn may omit a new refresh token on refresh; keep the old one.
            'refresh_token' => (string) ($tokens['refresh_token'] ?? $secrets['refresh_token'] ?? ''),
        ]);

        $updated = $connection->with([
            'token_expires_at' => time() + (int) ($tokens['expires_in'] ?? 0),
            'refresh_token_expires_at' => isset($tokens['refresh_token_expires_in'])
                ? time() + (int) $tokens['refresh_token_expires_in']
                : $connection->refreshTokenExpiresAt,
            'needs_reauth' => false,
        ]);

        $this->connections->save($updated);

        return $updated;
    }
}
