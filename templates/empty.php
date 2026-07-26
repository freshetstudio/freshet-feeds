<?php
/**
 * Rendered when a feed has no items (not yet fetched, fetch failed, or genuinely empty).
 *
 * Override: copy to {your-theme}/freshet-feeds/empty.php
 *
 * Available:
 *   $feed  \FreshetFeeds\Feed\Feed
 *   $items \FreshetFeeds\Item\ItemCollection  (empty)
 *   $args  array
 *
 * @package FreshetFeeds\Templates
 * @version 1.0.0
 */

use FreshetFeeds\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

// Error details are for site managers only; visitors see nothing.
if (!current_user_can('manage_options')) {
    return;
}

$error = Plugin::instance()->itemCache()->lastError($feed);
?>
<div class="freshet-feeds freshet-feeds--empty">
    <p>
        <?php
        printf(
            /* translators: %s: feed name */
            esc_html__('Freshet Feeds: no items to show for “%s”.', 'freshet-feeds'),
            esc_html($feed->name)
        );
        ?>
        <?php if ($error !== null) : ?>
            <br><code><?php echo esc_html($error); ?></code>
        <?php endif; ?>
        <em><?php esc_html_e('(Only administrators see this notice.)', 'freshet-feeds'); ?></em>
    </p>
</div>
