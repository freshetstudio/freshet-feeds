<?php

declare(strict_types=1);

namespace FreshetFeeds\Connection;

/**
 * Secret storage (client secret, access/refresh tokens) per connection,
 * in a non-autoloaded option, encrypted with sodium secretbox keyed off
 * AUTH_KEY. Falls back to plain storage when sodium/AUTH_KEY are missing
 * (ancient hosts) — documented, and no worse than every other WP plugin.
 */
final class TokenStore
{
    private const OPTION_PREFIX = 'freshet_feeds_tokens_';

    /**
     * @param array{client_secret?: string, access_token?: string, refresh_token?: string} $secrets
     */
    public function save(string $connectionId, array $secrets): void
    {
        $payload = wp_json_encode($secrets);

        $stored = $this->key() !== null
            ? 'enc:' . base64_encode($this->encrypt((string) $payload))
            : 'raw:' . base64_encode((string) $payload);

        $option = self::OPTION_PREFIX . sanitize_key($connectionId);

        // add first so autoload can be 'no'; update for subsequent writes.
        if (!add_option($option, $stored, '', false)) {
            update_option($option, $stored, false);
        }
    }

    /** @return array{client_secret?: string, access_token?: string, refresh_token?: string} */
    public function get(string $connectionId): array
    {
        $stored = get_option(self::OPTION_PREFIX . sanitize_key($connectionId));

        if (!is_string($stored) || strlen($stored) < 5) {
            return [];
        }

        $prefix = substr($stored, 0, 4);
        $body = base64_decode(substr($stored, 4), true);

        if ($body === false) {
            return [];
        }

        if ($prefix === 'enc:') {
            $body = $this->decrypt($body);

            if ($body === null) {
                return [];
            }
        }

        $secrets = json_decode($body, true);

        return is_array($secrets) ? $secrets : [];
    }

    public function delete(string $connectionId): void
    {
        delete_option(self::OPTION_PREFIX . sanitize_key($connectionId));
    }

    private function key(): ?string
    {
        if (!defined('AUTH_KEY') || AUTH_KEY === '' || !function_exists('sodium_crypto_secretbox')) {
            return null;
        }

        return sodium_crypto_generichash(AUTH_KEY, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    private function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return $nonce . sodium_crypto_secretbox($plaintext, $nonce, (string) $this->key());
    }

    private function decrypt(string $ciphertext): ?string
    {
        $key = $this->key();

        if ($key === null || strlen($ciphertext) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }

        $nonce = substr($ciphertext, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $box = substr($ciphertext, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plaintext = sodium_crypto_secretbox_open($box, $nonce, $key);

        return $plaintext === false ? null : $plaintext;
    }
}
