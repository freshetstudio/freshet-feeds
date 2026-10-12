<?php
/**
 * List layout: stacked rows.
 *
 * Override: copy to {your-theme}/freshet-feeds/layout-list.php
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
<div class="freshet-feeds__list">
    <?php foreach ($items as $item) : ?>
        <?php freshet_feeds_item($item, $feed); ?>
    <?php endforeach; ?>
</div>
