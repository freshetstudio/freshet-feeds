<?php

declare(strict_types=1);

namespace FreshetFeeds\Rest;

use FreshetFeeds\Feed\Feed;
use FreshetFeeds\Feed\FeedRepository;
use WP_REST_Response;

/**
 * Purpose-built route for the block editor's feed picker: only {id, name, slug}.
 * The feed CPT itself stays out of the REST API (its meta holds config).
 */
final class FeedsController
{
    public function __construct(private readonly FeedRepository $feeds)
    {
    }

    public function registerRoutes(): void
    {
        register_rest_route('freshet-feeds/v1', '/feeds', [
            'methods' => 'GET',
            'callback' => [$this, 'list'],
            'permission_callback' => static fn (): bool => current_user_can('edit_posts'),
        ]);
    }

    public function list(): WP_REST_Response
    {
        return new WP_REST_Response(array_map(
            static fn (Feed $feed): array => [
                'id' => $feed->id,
                'name' => $feed->name,
                'slug' => $feed->slug,
            ],
            $this->feeds->all()
        ));
    }
}
