<?php

declare(strict_types=1);

namespace FreshetFeeds\Provider\Rss;

use FreshetFeeds\Feed\Feed;
use FreshetFeeds\Item\ItemCollection;
use FreshetFeeds\Provider\FetchException;
use FreshetFeeds\Provider\ProviderInterface;

/**
 * Any RSS 2.0 or Atom feed. Also the escape hatch for platforms that expose
 * RSS without an open API (Mastodon: https://instance/@user.rss, Reddit:
 * subreddit/.rss, podcast feeds, ...).
 */
final class RssProvider implements ProviderInterface
{
    public function __construct(private readonly RssNormalizer $normalizer)
    {
    }

    public function id(): string
    {
        return 'rss';
    }

    public function label(): string
    {
        return __('RSS / Atom feed', 'freshet-feeds');
    }

    public function fetch(Feed $feed): ItemCollection
    {
        $url = (string) $feed->setting('feed_url', '');

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new FetchException(esc_html__('Feed URL is missing or invalid.', 'freshet-feeds'));
        }

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'limit_response_size' => 2 * MB_IN_BYTES,
            'user-agent' => 'FreshetFeeds/' . FRESHET_FEEDS_VERSION . '; ' . home_url(),
        ]);

        if (is_wp_error($response)) {
            throw new FetchException(esc_html($response->get_error_message()));
        }

        $status = (int) wp_remote_retrieve_response_code($response);

        if ($status !== 200) {
            throw new FetchException(esc_html(sprintf('Feed returned HTTP %d.', $status)));
        }

        try {
            $items = $this->normalizer->normalize(wp_remote_retrieve_body($response));
        } catch (\RuntimeException $e) {
            throw new FetchException(esc_html($e->getMessage()));
        }

        return (new ItemCollection($items))->take($feed->count);
    }

    public function settingsFields(): array
    {
        return [
            'feed_url' => [
                'label' => __('Feed URL', 'freshet-feeds'),
                'type' => 'text',
                'help' => __('Full URL of the RSS or Atom feed, e.g. https://example.com/feed/', 'freshet-feeds'),
                'required' => true,
            ],
        ];
    }
}
