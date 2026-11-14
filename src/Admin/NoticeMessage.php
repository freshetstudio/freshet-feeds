<?php

declare(strict_types=1);

namespace FreshetFeeds\Admin;

/**
 * The sentence an action wants to show after its redirect, kept on the server.
 *
 * The redirect carries only a random token; the sentence itself sits in a
 * short-lived transient keyed by that token and the user who caused it. So the
 * URL cannot choose what an administrator reads: a token nobody stored, or one
 * stored for a different user, resolves to nothing and the notice falls back
 * to its own fixed wording.
 */
final class NoticeMessage
{
    /** Query arg the token travels in. */
    public const ARG = 'freshet_feeds_msg';

    private const PREFIX = 'freshet_feeds_msg_';
    private const TTL = 300;

    /** Keep $message for the current user; returns the token for the redirect. */
    public static function stash(string $message): string
    {
        $token = bin2hex(random_bytes(16));

        set_transient(self::key($token), $message, self::TTL);

        return $token;
    }

    /**
     * The message stored under $token for the current user, once; '' when
     * there is none.
     */
    public static function take(string $token): string
    {
        if (preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
            return '';
        }

        $key = self::key($token);
        $message = get_transient($key);
        delete_transient($key);

        return is_string($message) ? $message : '';
    }

    private static function key(string $token): string
    {
        return self::PREFIX . get_current_user_id() . '_' . $token;
    }
}
