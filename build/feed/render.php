<?php
/**
 * Server render for the freshet-feeds/feed block — a thin skin over the same
 * freshet_feeds_render() path theme developers call directly.
 *
 * @var array{feedId: int, layout: string, count: int} $attributes
 */

if (!defined('ABSPATH')) {
    exit;
}

$freshet_feeds_feed = FreshetFeeds\Plugin::instance()->feeds()->find((int) $attributes['feedId']);

if ($freshet_feeds_feed === null) {
    return;
}

freshet_feeds_render($freshet_feeds_feed->slug, [
    'layout' => (string) $attributes['layout'],
    'count' => (int) $attributes['count'],
    'wrapper_attributes' => get_block_wrapper_attributes([
        'class' => 'freshet-feeds freshet-feeds--' . sanitize_html_class($freshet_feeds_feed->slug),
    ]),
]);
