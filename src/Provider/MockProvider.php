<?php

declare(strict_types=1);

namespace FreshetFeeds\Provider;

use FreshetFeeds\Feed\Feed;
use FreshetFeeds\Item\ItemAuthor;
use FreshetFeeds\Item\ItemCollection;
use FreshetFeeds\Provider\LinkedIn\PostNormalizer;

/**
 * Fixture-backed provider for development and template work: runs a real-shaped
 * LinkedIn payload through the same PostNormalizer the live client uses,
 * so anything built against it works unchanged with real LinkedIn data.
 */
final class MockProvider implements ProviderInterface
{
    public function __construct(
        private readonly PostNormalizer $normalizer,
        private readonly string $fixturePath,
    ) {
    }

    public function id(): string
    {
        return 'mock';
    }

    public function label(): string
    {
        return __('Mock (fixture data)', 'freshet-feeds');
    }

    public function fetch(Feed $feed): ItemCollection
    {
        if (!is_readable($this->fixturePath)) {
            throw new FetchException(esc_html(sprintf('Fixture not readable: %s', $this->fixturePath)));
        }

        $data = json_decode((string) file_get_contents($this->fixturePath), true);

        if (!is_array($data) || !is_array($data['elements'] ?? null)) {
            throw new FetchException(esc_html('Fixture is not a valid LinkedIn posts payload.'));
        }

        $imageUrlMap = is_array($data['_images'] ?? null) ? $data['_images'] : [];

        $organization = null;
        if (is_array($data['_organization'] ?? null)) {
            $organization = new ItemAuthor(
                name: (string) ($data['_organization']['name'] ?? ''),
                url: isset($data['_organization']['url']) ? (string) $data['_organization']['url'] : null,
            );
        }

        $items = array_map(
            fn (array $post) => $this->normalizer->normalize($post, $imageUrlMap, $organization),
            array_filter($data['elements'], 'is_array')
        );

        return (new ItemCollection($items))->take($feed->count);
    }

    public function settingsFields(): array
    {
        return [];
    }
}
