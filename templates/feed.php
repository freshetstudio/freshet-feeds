<?php
/**
 * Feed wrapper template.
 *
 * Override: copy to {your-theme}/freshet-feeds/feed.php
 *
 * Available:
 *   $feed   \FreshetFeeds\Feed\Feed              Feed config (name, slug, settings).
 *   $items  \FreshetFeeds\Item\ItemCollection    Normalized items — iterate directly.
 *   $layout string                             Layout name; resolves layout-{$layout}.php.
 *   $args   array                              Render args (block passes wrapper_attributes).
 *
 * Item getters return RAW values — escape in templates (esc_html, esc_url).
 *
 * @package FreshetFeeds\Templates
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$freshet_feeds_wrapper = $args['wrapper_attributes'] ?? sprintf(
    'class="freshet-feeds freshet-feeds--%s"',
    esc_attr(sanitize_html_class($feed->slug))
);

// Escaped at output: only a div with these attributes survives, whatever
// the wrapper string contained (block-supplied attrs come pre-escaped from
// get_block_wrapper_attributes(), but render args are an open API).
echo wp_kses('<div ' . $freshet_feeds_wrapper . '>', [
    'div' => ['class' => [], 'id' => [], 'style' => []],
]);
?>
    <?php
    freshet_feeds_template('layout-' . $layout, [
        'feed' => $feed,
        'items' => $items,
        'layout' => $layout,
        'args' => $args,
    ]);
    ?>
</div>
