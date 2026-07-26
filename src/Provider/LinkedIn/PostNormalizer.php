<?php

declare(strict_types=1);

namespace FreshetFeeds\Provider\LinkedIn;

use DateTimeImmutable;
use DateTimeZone;
use FreshetFeeds\Item\Item;
use FreshetFeeds\Item\ItemAuthor;
use FreshetFeeds\Item\ItemImage;

/**
 * Raw LinkedIn /rest/posts element → normalized Item. Pure — no WP, no network —
 * so the mock fixture and the live client share the exact same mapping.
 */
final class PostNormalizer
{
    /**
     * @param array<string, mixed> $post Raw post element from /rest/posts.
     * @param array<string, array{url: string, width?: int, height?: int, alt?: string}> $imageUrlMap
     *        Resolved image URNs → download URL (+ dimensions). Signed LinkedIn URLs expire;
     *        ImageStore localizes them later in the pipeline.
     */
    public function normalize(array $post, array $imageUrlMap = [], ?ItemAuthor $organization = null): Item
    {
        $urn = (string) ($post['id'] ?? '');

        $publishedMs = $post['publishedAt'] ?? $post['createdAt'] ?? 0;
        $date = (new DateTimeImmutable('@' . intdiv((int) $publishedMs, 1000)))
            ->setTimezone(new DateTimeZone('UTC'));

        return new Item(
            id: $urn,
            provider: 'linkedin',
            url: 'https://www.linkedin.com/feed/update/' . rawurlencode($urn),
            date: $date,
            content: $this->plainCommentary((string) ($post['commentary'] ?? '')),
            title: $this->title($post),
            image: $this->image($post, $imageUrlMap),
            author: $organization,
            raw: $post,
        );
    }

    /** Shared articles carry a title; plain posts usually don't. */
    private function title(array $post): ?string
    {
        $title = $post['content']['article']['title'] ?? null;

        return is_string($title) && $title !== '' ? $title : null;
    }

    /** @param array<string, array{url: string, width?: int, height?: int, alt?: string}> $imageUrlMap */
    private function image(array $post, array $imageUrlMap): ?ItemImage
    {
        [$urn, $alt] = $this->imageUrn($post);

        if ($urn === null || !isset($imageUrlMap[$urn])) {
            return null;
        }

        $resolved = $imageUrlMap[$urn];

        return new ItemImage(
            remoteUrl: $resolved['url'],
            alt: $alt ?? ($resolved['alt'] ?? null),
            width: $resolved['width'] ?? null,
            height: $resolved['height'] ?? null,
        );
    }

    /**
     * First image URN referenced by the post: single media, article thumbnail,
     * or the first of a multi-image post.
     *
     * @return array{0: ?string, 1: ?string} [urn, altText]
     */
    public function imageUrn(array $post): array
    {
        $content = $post['content'] ?? [];

        $mediaId = $content['media']['id'] ?? null;
        if (is_string($mediaId) && str_starts_with($mediaId, 'urn:li:image:')) {
            return [$mediaId, $content['media']['altText'] ?? null];
        }

        $thumbnail = $content['article']['thumbnail'] ?? null;
        if (is_string($thumbnail) && str_starts_with($thumbnail, 'urn:li:image:')) {
            return [$thumbnail, $content['article']['thumbnailAltText'] ?? null];
        }

        $first = $content['multiImage']['images'][0] ?? null;
        if (is_array($first) && is_string($first['id'] ?? null)) {
            return [$first['id'], $first['altText'] ?? null];
        }

        return [null, null];
    }

    /**
     * Strip LinkedIn "little format" from commentary:
     * {hashtag|\#|Tag} → #Tag, @[Name](urn:...) → Name, and unescape \-escaped chars.
     */
    public function plainCommentary(string $commentary): string
    {
        $text = preg_replace('/\{hashtag\|\\\\?#\|([^}]+)\}/u', '#$1', $commentary) ?? $commentary;
        $text = preg_replace('/@\[([^\]]+)\]\([^)]*\)/u', '$1', $text) ?? $text;
        $text = preg_replace('/\\\\([(){}<>\[\]|*_~@#\\\\])/u', '$1', $text) ?? $text;

        return trim($text);
    }
}
