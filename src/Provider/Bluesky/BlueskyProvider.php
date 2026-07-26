<?php

declare(strict_types=1);

namespace FreshetFeeds\Provider\Bluesky;

use FreshetFeeds\Feed\Feed;
use FreshetFeeds\Item\ItemCollection;
use FreshetFeeds\Provider\FetchException;
use FreshetFeeds\Provider\ProviderInterface;

/**
 * Bluesky profile posts via the public AppView API — no auth, no app, no key.
 */
final class BlueskyProvider implements ProviderInterface
{
    private const API = 'https://public.api.bsky.app/xrpc/app.bsky.feed.getAuthorFeed';

    public function __construct(private readonly BlueskyNormalizer $normalizer)
    {
    }

    public function id(): string
    {
        return 'bluesky';
    }

    public function label(): string
    {
        return __('Bluesky (profile)', 'freshet-feeds');
    }

    public function fetch(Feed $feed): ItemCollection
    {
        $handle = ltrim(trim((string) $feed->setting('handle', '')), '@');

        if ($handle === '') {
            throw new FetchException(esc_html__('Bluesky handle is missing.', 'freshet-feeds'));
        }

        $url = add_query_arg([
            'actor' => rawurlencode($handle),
            'limit' => min(100, max(1, $feed->count * 2)), // headroom: reposts/replies are filtered out.
            'filter' => 'posts_no_replies',
        ], self::API);

        $response = wp_remote_get($url, ['timeout' => 15, 'limit_response_size' => 2 * MB_IN_BYTES]);

        if (is_wp_error($response)) {
            throw new FetchException(esc_html($response->get_error_message()));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ((int) wp_remote_retrieve_response_code($response) !== 200 || !is_array($body)) {
            $message = is_array($body) ? (string) ($body['message'] ?? '') : '';

            throw new FetchException(esc_html(sprintf(
                'Bluesky API error%s — check the handle (e.g. "name.bsky.social").',
                $message !== '' ? ': ' . $message : ''
            )));
        }

        return (new ItemCollection($this->normalizer->normalize($body)))->take($feed->count);
    }

    public function settingsFields(): array
    {
        return [
            'handle' => [
                'label' => __('Handle', 'freshet-feeds'),
                'type' => 'text',
                'help' => __('The profile handle, e.g. name.bsky.social or a custom domain handle.', 'freshet-feeds'),
                'required' => true,
            ],
        ];
    }
}
