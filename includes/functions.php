<?php
/**
 * Freshet Feeds — public developer API.
 *
 * @package FreshetFeeds
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use FreshetFeeds\Feed\Feed;
use FreshetFeeds\Item\Item;
use FreshetFeeds\Item\ItemCollection;
use FreshetFeeds\Plugin;

if (!function_exists('freshet-feeds')) {
    /**
     * The loop API: cached items for a feed, ready to iterate in any template.
     *
     *     foreach ( freshet_feeds( 'linkedin-main' ) as $item ) {
     *         echo esc_html( $item->title( 'Untitled' ) );
     *     }
     *
     * Never blocks on the remote API (except a feed's very first fetch):
     * stale items are served immediately and a background refresh is queued.
     */
    function freshet_feeds(string $feed_slug): ItemCollection
    {
        return Plugin::instance()->items($feed_slug);
    }
}

if (!function_exists('freshet_feeds_render')) {
    /**
     * Render a feed through the template chain (feed.php → layout-*.php → item.php).
     *
     * @param array{layout?: string, count?: int, wrapper_attributes?: string} $args
     */
    function freshet_feeds_render(string $feed_slug, array $args = []): void
    {
        Plugin::instance()->render($feed_slug, $args);
    }
}

if (!function_exists('freshet_feeds_item')) {
    /**
     * Render one item through the item template hierarchy — most specific wins:
     *
     *   {theme}/freshet-feeds/item-{feed-slug}.php   e.g. item-linkedin-main.php
     *   {theme}/freshet-feeds/item-{provider}.php    e.g. item-youtube.php
     *   {theme}/freshet-feeds/item.php
     *   (each name also falls back to the plugin's templates/ dir)
     *
     * Layouts call this per item; call it yourself in custom layouts.
     */
    function freshet_feeds_item(Item $item, Feed $feed): void
    {
        Plugin::instance()->templates()->renderFirst(
            [
                'item-' . $feed->slug,
                'item-' . $item->provider,
                'item',
            ],
            ['item' => $item, 'feed' => $feed]
        );
    }
}

if (!function_exists('freshet_feeds_template')) {
    /**
     * get_template_part() equivalent for Freshet Feeds templates — resolves
     * through child theme → parent theme → plugin. For use inside overrides.
     *
     * @param array<string, mixed> $vars Extracted into the template's scope.
     */
    function freshet_feeds_template(string $name, array $vars = []): void
    {
        Plugin::instance()->templates()->render($name, $vars);
    }
}
