<?php
/**
 * Grid layout: responsive card grid.
 *
 * Override: copy to {your-theme}/freshet-feeds/layout-grid.php
 * Custom layouts: add {your-theme}/freshet-feeds/layout-{name}.php and pass
 * that name via the block's layout setting or freshet_feeds_render() args.
 *
 * Available: $feed, $items, $layout, $args (see feed.php).
 *
 * Items resolve through the item hierarchy:
 * item-{feed-slug}.php → item-{provider}.php → item.php
 *
 * @package FreshetFeeds\Templates
 * @version 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="freshet-feeds__grid">
    <?php foreach ($items as $item) : ?>
        <?php freshet_feeds_item($item, $feed); ?>
    <?php endforeach; ?>
</div>
