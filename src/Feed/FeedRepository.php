<?php

declare(strict_types=1);

namespace FreshetFeeds\Feed;

use RuntimeException;
use WP_Post;

/**
 * Maps Feed value objects onto the (non-public) freshet_feeds_feed CPT.
 * Config lives in one meta blob; item cache meta lives alongside (see ItemCache).
 */
final class FeedRepository
{
    public const POST_TYPE = 'freshet_feeds_feed';
    private const META_CONFIG = '_freshet_feeds_config';

    public static function registerPostType(): void
    {
        register_post_type(self::POST_TYPE, [
            'label' => __('Feeds', 'freshet-feeds'),
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'supports' => ['title'],
        ]);
    }

    public function find(int $id): ?Feed
    {
        $post = get_post($id);

        if (!$post instanceof WP_Post || $post->post_type !== self::POST_TYPE) {
            return null;
        }

        return $this->hydrate($post);
    }

    public function findBySlug(string $slug): ?Feed
    {
        $posts = get_posts([
            'post_type' => self::POST_TYPE,
            'name' => sanitize_title($slug),
            'post_status' => 'publish',
            'numberposts' => 1,
        ]);

        return $posts === [] ? null : $this->hydrate($posts[0]);
    }

    /** @return list<Feed> */
    public function all(): array
    {
        $posts = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        return array_map(fn (WP_Post $post): Feed => $this->hydrate($post), $posts);
    }

    public function save(Feed $feed): Feed
    {
        $isNew = $feed->id === 0;

        $postData = [
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $feed->name,
            'post_name' => $feed->slug !== '' ? sanitize_title($feed->slug) : sanitize_title($feed->name),
        ];

        if (!$isNew) {
            $postData['ID'] = $feed->id;
            $result = wp_update_post($postData, true);
        } else {
            $result = wp_insert_post($postData, true);
        }

        if (is_wp_error($result)) {
            throw new RuntimeException(esc_html($result->get_error_message()));
        }

        $id = (int) $result;

        update_post_meta($id, self::META_CONFIG, wp_json_encode([
            'provider' => $feed->providerId,
            'settings' => $feed->settings,
            'count' => $feed->count,
            'ttl' => $feed->ttl,
            'default_layout' => $feed->defaultLayout,
        ]));

        $saved = $this->find($id);

        if ($saved === null) {
            throw new RuntimeException(esc_html__('Feed could not be saved.', 'freshet-feeds'));
        }

        return $saved;
    }

    public function delete(int $id): bool
    {
        $feed = $this->find($id);

        if ($feed === null) {
            return false;
        }

        return wp_delete_post($id, true) !== false;
    }

    private function hydrate(WP_Post $post): Feed
    {
        $config = json_decode((string) get_post_meta($post->ID, self::META_CONFIG, true), true);
        $config = is_array($config) ? $config : [];

        return new Feed(
            id: $post->ID,
            name: $post->post_title,
            slug: $post->post_name,
            providerId: (string) ($config['provider'] ?? 'mock'),
            settings: is_array($config['settings'] ?? null) ? $config['settings'] : [],
            count: (int) ($config['count'] ?? Feed::DEFAULT_COUNT),
            ttl: (int) ($config['ttl'] ?? Feed::DEFAULT_TTL),
            defaultLayout: (string) ($config['default_layout'] ?? 'grid'),
        );
    }
}
