<?php

declare(strict_types=1);

namespace FreshetFeeds\Provider\LinkedIn;

use FreshetFeeds\Feed\Feed;

/**
 * The hybrid-connection seam: the same LinkedInProvider works whether posts
 * come from the site owner's own LinkedIn app (ByoLinkedInClient) or the
 * vendor proxy service (ProxyLinkedInClient).
 */
interface LinkedInClientInterface
{
    /**
     * Raw /rest/posts elements for an organization.
     *
     * @return list<array<string, mixed>>
     * @throws \FreshetFeeds\Provider\FetchException
     */
    public function getOrganizationPosts(Feed $feed, string $orgUrn, int $count): array;

    /**
     * Resolve image URNs to signed download URLs.
     *
     * @param list<string> $imageUrns
     * @return array<string, array{url: string, width?: int, height?: int, alt?: string}>
     * @throws \FreshetFeeds\Provider\FetchException
     */
    public function resolveImages(Feed $feed, array $imageUrns): array;
}
