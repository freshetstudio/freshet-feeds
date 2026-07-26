<?php
/**
 * Single feed item — the template you will most likely override.
 *
 * Override: copy to {your-theme}/freshet-feeds/item.php
 *
 * Available:
 *   $item \FreshetFeeds\Item\Item   One normalized item.
 *   $feed \FreshetFeeds\Feed\Feed   The feed it belongs to.
 *
 * Item API (raw values — escape here):
 *   $item->title( $fallback )   ?string  Post/article title (LinkedIn posts often have none).
 *   $item->date( $format )      string   Localized date; '' = site format.
 *   $item->content()            string   Full plain-text body.
 *   $item->excerpt( $words )    string   Trimmed body.
 *   $item->url()                string   Permalink on the source platform.
 *   $item->hasImage()           bool
 *   $item->image()              ?string  Image URL (local copy preferred).
 *   $item->imageTag( $attrs )   string   Ready-made, escaped <img>.
 *   $item->author()             ?ItemAuthor  ->name, ->url, ->imageUrl.
 *   $item->raw()                array    Untouched provider payload.
 *
 * @package FreshetFeeds\Templates
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<article class="freshet-feeds__item">
    <?php if ($item->hasImage()) : ?>
        <a class="freshet-feeds__item-media" href="<?php echo esc_url($item->url()); ?>" target="_blank" rel="noopener noreferrer">
            <?php echo $item->imageTag(['class' => 'freshet-feeds__item-image']); // phpcs:ignore WordPress.Security.EscapeOutput -- imageTag() escapes internally. ?>
        </a>
    <?php endif; ?>

    <div class="freshet-feeds__item-body">
        <time class="freshet-feeds__item-date" datetime="<?php echo esc_attr($item->datetime()->format('c')); ?>">
            <?php echo esc_html($item->date()); ?>
        </time>

        <?php if ($item->title() !== null) : ?>
            <h3 class="freshet-feeds__item-title">
                <a href="<?php echo esc_url($item->url()); ?>" target="_blank" rel="noopener noreferrer">
                    <?php echo esc_html($item->title()); ?>
                </a>
            </h3>
        <?php endif; ?>

        <p class="freshet-feeds__item-excerpt"><?php echo esc_html($item->excerpt(40)); ?></p>

        <a class="freshet-feeds__item-link" href="<?php echo esc_url($item->url()); ?>" target="_blank" rel="noopener noreferrer">
            <?php esc_html_e('View post', 'freshet-feeds'); ?>
        </a>
    </div>
</article>
