<?php

declare(strict_types=1);

namespace FreshetFeeds\Provider;

use FreshetFeeds\Feed\Feed;
use FreshetFeeds\Item\ItemAuthor;
use FreshetFeeds\Item\ItemCollection;
use FreshetFeeds\Provider\Mock\FixtureNormalizer;

/**
 * Fixture-backed provider for development and template work: runs a bundled
 * sample payload through FixtureNormalizer and the shared item model, so
 * templates can be built and styled offline with no credentials.
 */
final class MockProvider implements ProviderInterface
{
    public const ID = 'mock';

    public function __construct(
        private readonly FixtureNormalizer $normalizer,
        private readonly string $fixturePath,
    ) {
    }

    public function id(): string
    {
        return self::ID;
    }

    public function label(): string
    {
        return __('Mock (fixture data)', 'freshet-feeds');
    }

    /**
     * Fixture posts must never surface as a site's real content. The admin
     * dropdown hides this provider in `production`, but a feed created before
     * the environment flipped would keep fetching — so the environment is the
     * gate, not the UI.
     */
    public static function isAvailable(): bool
    {
        return wp_get_environment_type() !== 'production';
    }

    public function fetch(Feed $feed): ItemCollection
    {
        if (!self::isAvailable()) {
            throw new FetchException(esc_html__(
                'The mock provider is disabled in production environments. Pick a real provider for this feed.',
                'freshet-feeds'
            ));
        }

        if (!is_readable($this->fixturePath)) {
            throw new FetchException(esc_html(sprintf('Fixture not readable: %s', $this->fixturePath)));
        }

        $data = json_decode((string) file_get_contents($this->fixturePath), true);

        if (!is_array($data) || !is_array($data['elements'] ?? null)) {
            throw new FetchException(esc_html('Fixture is not a valid mock posts payload.'));
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
