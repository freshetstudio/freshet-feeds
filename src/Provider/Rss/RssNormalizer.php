<?php

declare(strict_types=1);

namespace FreshetFeeds\Provider\Rss;

use DateTimeImmutable;
use DateTimeZone;
use FreshetFeeds\Item\Item;
use FreshetFeeds\Item\ItemAuthor;
use FreshetFeeds\Item\ItemImage;

/**
 * RSS 2.0 / Atom → normalized Items. Pure (no WP, no network) so it's fully
 * unit-testable; deliberately tolerant — real-world feeds are messy.
 */
final class RssNormalizer
{
    private const NS_MEDIA = 'http://search.yahoo.com/mrss/';
    private const NS_CONTENT = 'http://purl.org/rss/1.0/modules/content/';
    private const NS_ATOM = 'http://www.w3.org/2005/Atom';

    /**
     * @return list<Item>
     * @throws \RuntimeException When the XML is unparseable or not a feed.
     */
    public function normalize(string $xml, string $providerId = 'rss'): array
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $root = simplexml_load_string($xml);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($root === false) {
            throw new \RuntimeException('Feed is not valid XML.');
        }

        if ($root->getName() === 'feed') {
            return $this->fromAtom($root, $providerId);
        }

        if (isset($root->channel)) {
            return $this->fromRss($root, $providerId);
        }

        throw new \RuntimeException('XML is neither an RSS channel nor an Atom feed.');
    }

    /** @return list<Item> */
    private function fromRss(\SimpleXMLElement $root, string $providerId): array
    {
        $channel = $root->channel;
        $author = trim((string) $channel->title) !== ''
            ? new ItemAuthor(trim((string) $channel->title), trim((string) $channel->link) ?: null)
            : null;

        $items = [];

        foreach ($channel->item as $entry) {
            $link = trim((string) $entry->link);
            $guid = trim((string) $entry->guid) ?: $link;

            $contentNs = $entry->children(self::NS_CONTENT);
            $html = trim((string) ($contentNs->encoded ?? '')) ?: trim((string) $entry->description);

            $items[] = new Item(
                id: $providerId . ':' . md5($guid),
                provider: $providerId,
                url: $link,
                date: $this->date((string) $entry->pubDate),
                content: $this->plainText($html),
                title: trim((string) $entry->title) ?: null,
                image: $this->rssImage($entry),
                author: $author,
                raw: $this->toArray($entry),
            );
        }

        return $items;
    }

    /** @return list<Item> */
    private function fromAtom(\SimpleXMLElement $root, string $providerId): array
    {
        $feedTitle = trim((string) $root->title);
        $author = $feedTitle !== '' ? new ItemAuthor($feedTitle) : null;

        $items = [];

        foreach ($root->entry as $entry) {
            $link = '';

            foreach ($entry->link as $candidate) {
                $rel = (string) $candidate['rel'];

                if ($rel === '' || $rel === 'alternate') {
                    $link = (string) $candidate['href'];
                    break;
                }
            }

            $html = trim((string) $entry->content) ?: trim((string) $entry->summary);
            $id = trim((string) $entry->id) ?: $link;

            $entryAuthor = trim((string) ($entry->author->name ?? ''));

            $items[] = new Item(
                id: $providerId . ':' . md5($id),
                provider: $providerId,
                url: $link,
                date: $this->date((string) ($entry->published ?? '') ?: (string) $entry->updated),
                content: $this->plainText($html),
                title: trim((string) $entry->title) ?: null,
                image: $this->mediaImage($entry),
                author: $entryAuthor !== '' ? new ItemAuthor($entryAuthor) : $author,
                raw: $this->toArray($entry),
            );
        }

        return $items;
    }

    /** First usable image: media:content / media:thumbnail / image enclosure. */
    private function rssImage(\SimpleXMLElement $entry): ?ItemImage
    {
        $image = $this->mediaImage($entry);

        if ($image !== null) {
            return $image;
        }

        foreach ($entry->enclosure as $enclosure) {
            if (str_starts_with((string) $enclosure['type'], 'image/') && (string) $enclosure['url'] !== '') {
                return new ItemImage(remoteUrl: (string) $enclosure['url']);
            }
        }

        return null;
    }

    private function mediaImage(\SimpleXMLElement $entry): ?ItemImage
    {
        $media = $entry->children(self::NS_MEDIA);

        // media:group wraps media:* in some feeds (YouTube does this).
        $scopes = [$media, $media->group ?? null];

        foreach ($scopes as $scope) {
            if ($scope === null) {
                continue;
            }

            foreach (['thumbnail', 'content'] as $tag) {
                foreach ($scope->{$tag} as $node) {
                    // After children($ns), unprefixed attributes need an explicit
                    // no-namespace attributes() call — $node['url'] comes back empty.
                    $attrs = $node->attributes() ?? new \SimpleXMLElement('<x/>');
                    $url = (string) $attrs['url'];
                    $type = (string) $attrs['type'];

                    if ($url !== '' && ($type === '' || str_starts_with($type, 'image/'))) {
                        return new ItemImage(
                            remoteUrl: $url,
                            width: (int) $attrs['width'] ?: null,
                            height: (int) $attrs['height'] ?: null,
                        );
                    }
                }
            }
        }

        return null;
    }

    private function date(string $value): DateTimeImmutable
    {
        $timestamp = $value !== '' ? strtotime($value) : false;

        return (new DateTimeImmutable('@' . ($timestamp !== false ? $timestamp : 0)))
            ->setTimezone(new DateTimeZone('UTC'));
    }

    private function plainText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- normalizer is intentionally WP-free (pure, unit-tested without WordPress).

        return trim((string) preg_replace('/[ \t]*\n[ \t]*/', "\n", $text));
    }

    /** @return array<string, mixed> */
    private function toArray(\SimpleXMLElement $entry): array
    {
        $decoded = json_decode((string) json_encode($entry), true);

        return is_array($decoded) ? $decoded : [];
    }
}
