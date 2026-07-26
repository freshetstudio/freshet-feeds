<?php

declare(strict_types=1);

namespace FreshetFeeds\Provider;

use FreshetFeeds\Feed\Feed;
use FreshetFeeds\Item\ItemCollection;

/**
 * A feed source. Implementations fetch remote data and return normalized Items —
 * everything above this seam (cache, templates, block) is provider-agnostic.
 */
interface ProviderInterface
{
    public function id(): string;

    public function label(): string;

    /**
     * Fetch fresh items for a feed. Networked, may be slow — only FeedRunner calls this.
     *
     * @throws FetchException On any failure; the runner preserves stale cache.
     */
    public function fetch(Feed $feed): ItemCollection;

    /**
     * Provider-specific feed settings for the admin form.
     *
     * @return array<string, array{label: string, type: string, help?: string, options?: array<string, string>, required?: bool}>
     */
    public function settingsFields(): array;
}
