<?php

declare(strict_types=1);

namespace FreshetFeeds\Provider\Bluesky;

use DateTimeImmutable;
use DateTimeZone;
use FreshetFeeds\Item\Item;
use FreshetFeeds\Item\ItemAuthor;
use FreshetFeeds\Item\ItemImage;

/**
 * app.bsky.feed.getAuthorFeed response → normalized Items. Pure.
 */
final class BlueskyNormalizer
{
    /**
     * @param array<string, mixed> $response Decoded getAuthorFeed payload.
     * @return list<Item>
     */
    public function normalize(array $response): array
    {
        $items = [];

        foreach ($response['feed'] ?? [] as $entry) {
            if (!is_array($entry) || isset($entry['reason'])) {
                continue; // reason = repost/pin — only own original posts.
            }

            $post = $entry['post'] ?? null;

            if (!is_array($post)) {
                continue;
            }

            $uri = (string) ($post['uri'] ?? '');
            $rkey = substr($uri, (int) strrpos($uri, '/') + 1);
            $handle = (string) ($post['author']['handle'] ?? '');

            $created = (string) ($post['record']['createdAt'] ?? '');
            $timestamp = $created !== '' ? strtotime($created) : false;

            $items[] = new Item(
                id: $uri,
                provider: 'bluesky',
                url: sprintf('https://bsky.app/profile/%s/post/%s', rawurlencode($handle), rawurlencode($rkey)),
                date: (new DateTimeImmutable('@' . ($timestamp !== false ? $timestamp : 0)))
                    ->setTimezone(new DateTimeZone('UTC')),
                content: (string) ($post['record']['text'] ?? ''),
                title: null,
                image: $this->image($post),
                author: $this->author($post),
                raw: $post,
            );
        }

        return $items;
    }

    /** @param array<string, mixed> $post */
    private function image(array $post): ?ItemImage
    {
        // Embedded images (app.bsky.embed.images#view) or external link card thumb.
        $first = $post['embed']['images'][0] ?? null;

        if (is_array($first) && is_string($first['fullsize'] ?? null)) {
            return new ItemImage(
                remoteUrl: $first['fullsize'],
                alt: (string) ($first['alt'] ?? '') ?: null,
                width: isset($first['aspectRatio']['width']) ? (int) $first['aspectRatio']['width'] : null,
                height: isset($first['aspectRatio']['height']) ? (int) $first['aspectRatio']['height'] : null,
            );
        }

        $thumb = $post['embed']['external']['thumb'] ?? null;

        return is_string($thumb) && $thumb !== '' ? new ItemImage(remoteUrl: $thumb) : null;
    }

    /** @param array<string, mixed> $post */
    private function author(array $post): ?ItemAuthor
    {
        $author = $post['author'] ?? null;

        if (!is_array($author)) {
            return null;
        }

        $handle = (string) ($author['handle'] ?? '');
        $name = (string) ($author['displayName'] ?? '') ?: $handle;

        if ($name === '') {
            return null;
        }

        return new ItemAuthor(
            name: $name,
            url: $handle !== '' ? 'https://bsky.app/profile/' . rawurlencode($handle) : null,
            imageUrl: is_string($author['avatar'] ?? null) ? $author['avatar'] : null,
        );
    }
}
