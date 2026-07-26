<?php

declare(strict_types=1);

namespace FreshetFeeds\Cache;

use FreshetFeeds\Feed\Feed;
use FreshetFeeds\Item\Item;
use FreshetFeeds\Item\ItemCollection;

/**
 * Localizes item images into uploads/freshet-feeds/{feed_id}/ — LinkedIn download
 * URLs are signed and expire within days, so hotlinking is not an option.
 * Plain files (not attachments): no media-library pollution, trivially pruned.
 */
final class ImageStore
{
    /** Wall-clock budget per localize() run; items past it keep their remote URL. */
    private const TIME_BUDGET_SECONDS = 20;

    /**
     * Download each item's image and rewrite it to the local copy.
     * Failures fall back to the remote URL; the feed still renders.
     * Bounded: after TIME_BUDGET_SECONDS the remaining downloads are skipped
     * (they get picked up on the next cron refresh via the content-hash cache).
     */
    public function localize(Feed $feed, ItemCollection $items): ItemCollection
    {
        $dir = $this->dir($feed);
        $kept = [];
        $deadline = microtime(true) + self::TIME_BUDGET_SECONDS;

        $localized = $items->map(function (Item $item) use ($feed, $dir, &$kept, $deadline): Item {
            if ($item->image === null || $item->image->remoteUrl === '') {
                return $item;
            }

            $filename = $this->download($item->image->remoteUrl, $dir, $item->id, $deadline);

            if ($filename === null) {
                return $item;
            }

            $kept[] = $filename;

            return $item->withImage(
                $item->image->withLocalUrl($this->baseUrl($feed) . '/' . $filename)
            );
        });

        $this->prune($dir, $kept);

        return $localized;
    }

    public function deleteForFeed(int $feedId): void
    {
        $upload = wp_upload_dir();
        $this->removeDir(trailingslashit($upload['basedir']) . 'freshet-feeds/' . $feedId);
    }

    private function download(string $url, string $dir, string $itemId, float $deadline = PHP_FLOAT_MAX): ?string
    {
        if (!wp_mkdir_p($dir)) {
            return null;
        }

        // Content-addressed by item id: unchanged posts skip the download entirely.
        $hash = substr(md5($itemId), 0, 16);

        foreach (['jpg', 'png', 'webp', 'gif'] as $ext) {
            if (is_readable($dir . '/' . $hash . '.' . $ext)) {
                return $hash . '.' . $ext;
            }
        }

        if (microtime(true) > $deadline) {
            return null; // budget spent — keep remote URL, retry next refresh.
        }

        // SSRF guard: image URLs come from REMOTE feed content, not admin input.
        // wp_safe_remote_get() rejects private ranges and odd ports; link-local
        // (cloud metadata, 169.254.0.0/16) needs an explicit extra check.
        $host = (string) wp_parse_url($url, PHP_URL_HOST);

        if ($host === '' || str_starts_with($host, '169.254.')) {
            return null;
        }

        $response = wp_safe_remote_get($url, [
            'timeout' => 5,
            'limit_response_size' => 5 * MB_IN_BYTES,
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        // Strict image/* allowlist — never write non-image bytes to a public path.
        $type = wp_remote_retrieve_header($response, 'content-type');
        $type = strtolower(trim(explode(';', is_array($type) ? ($type[0] ?? '') : (string) $type)[0]));

        $ext = match ($type) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => null,
        };

        $body = wp_remote_retrieve_body($response);

        if ($ext === null || $body === '') {
            return null;
        }

        $filename = $hash . '.' . $ext;

        return file_put_contents($dir . '/' . $filename, $body) !== false ? $filename : null; // phpcs:ignore PluginCheck.CodeAnalysis.WriteFile.PluginDirectoryWrite -- $dir is inside wp_upload_dir(), never the plugin folder.
    }

    /** @param list<string> $keep */
    private function prune(string $dir, array $keep): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $file) {
            if ($file[0] !== '.' && !in_array($file, $keep, true)) {
                @unlink($dir . '/' . $file); // phpcs:ignore PluginCheck.CodeAnalysis.WriteFile.PluginDirectoryWrite,WordPress.WP.AlternativeFunctions -- pruning our own files in wp_upload_dir().
            }
        }
    }

    private function dir(Feed $feed): string
    {
        $upload = wp_upload_dir();

        return trailingslashit($upload['basedir']) . 'freshet-feeds/' . $feed->id;
    }

    private function baseUrl(Feed $feed): string
    {
        $upload = wp_upload_dir();

        return trailingslashit($upload['baseurl']) . 'freshet-feeds/' . $feed->id;
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $file) {
            if ($file[0] !== '.') {
                @unlink($dir . '/' . $file); // phpcs:ignore PluginCheck.CodeAnalysis.WriteFile.PluginDirectoryWrite,WordPress.WP.AlternativeFunctions -- pruning our own files in wp_upload_dir().
            }
        }

        @rmdir($dir); // phpcs:ignore PluginCheck.CodeAnalysis.WriteFile.PluginDirectoryWrite,WordPress.WP.AlternativeFunctions -- removing our own dir in wp_upload_dir().
    }
}
