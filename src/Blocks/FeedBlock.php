<?php

declare(strict_types=1);

namespace FreshetFeeds\Blocks;

/**
 * Registers the freshet-feeds/feed block from the built block.json.
 * Rendering happens in blocks/feed/render.php via the same public API
 * (freshet_feeds_render) theme developers use directly.
 */
final class FeedBlock
{
    public function register(): void
    {
        $blockDir = FRESHET_FEEDS_DIR . 'build/feed';

        if (is_readable($blockDir . '/block.json')) {
            register_block_type($blockDir);
        }
    }
}
