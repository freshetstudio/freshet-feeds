<?php

declare(strict_types=1);

namespace FreshetFeeds\Provider\LinkedIn;

use FreshetFeeds\Connection\ConnectionRepository;
use FreshetFeeds\Feed\Feed;
use FreshetFeeds\Item\ItemAuthor;
use FreshetFeeds\Item\ItemCollection;
use FreshetFeeds\License\LicenseInterface;
use FreshetFeeds\Provider\ProviderInterface;

/**
 * LinkedIn company-page posts: client fetch → image URN resolution → normalization.
 */
final class LinkedInProvider implements ProviderInterface
{
    public function __construct(
        private readonly LinkedInClientInterface $client,
        private readonly PostNormalizer $normalizer,
        private readonly ConnectionRepository $connections,
        private readonly LicenseInterface $license,
    ) {
    }

    public function id(): string
    {
        return 'linkedin';
    }

    public function label(): string
    {
        return __('LinkedIn (company page)', 'freshet-feeds');
    }

    public function fetch(Feed $feed): ItemCollection
    {
        $orgId = (string) $feed->setting('organization_id', '');
        $orgUrn = str_starts_with($orgId, 'urn:') ? $orgId : 'urn:li:organization:' . $orgId;

        $posts = $this->client()->getOrganizationPosts($feed, $orgUrn, $feed->count);

        $imageUrns = [];
        foreach ($posts as $post) {
            [$urn] = $this->normalizer->imageUrn($post);
            if ($urn !== null) {
                $imageUrns[] = $urn;
            }
        }

        $imageUrlMap = $this->client()->resolveImages($feed, array_values(array_unique($imageUrns)));

        $organization = null;
        $orgName = (string) $feed->setting('organization_name', '');
        if ($orgName !== '') {
            $orgSlug = (string) $feed->setting('organization_slug', '');

            $organization = new ItemAuthor(
                name: $orgName,
                url: $orgSlug !== '' ? 'https://www.linkedin.com/company/' . rawurlencode($orgSlug) : null,
            );
        }

        $items = array_map(
            fn (array $post) => $this->normalizer->normalize($post, $imageUrlMap, $organization),
            $posts
        );

        return (new ItemCollection($items))->take($feed->count);
    }

    public function settingsFields(): array
    {
        return [
            'connection_id' => [
                'label' => __('LinkedIn connection', 'freshet-feeds'),
                'type' => 'connection',
                'required' => true,
            ],
            'organization_id' => [
                'label' => __('Organization ID', 'freshet-feeds'),
                'type' => 'text',
                'help' => __('The numeric ID of your company page (from its admin URL), e.g. 2414183.', 'freshet-feeds'),
                'required' => true,
            ],
            'organization_name' => [
                'label' => __('Organization name', 'freshet-feeds'),
                'type' => 'text',
                'help' => __('Shown as the item author in templates.', 'freshet-feeds'),
            ],
            'organization_slug' => [
                'label' => __('Organization slug', 'freshet-feeds'),
                'type' => 'text',
                'help' => __('The company page slug, e.g. "acme-corp" for linkedin.com/company/acme-corp.', 'freshet-feeds'),
            ],
        ];
    }

    /**
     * The managed pipeline (vendor proxy) is on when the license entitles it;
     * the filter remains a manual override in either direction. The class
     * check mirrors Plugin.php's license-stack file checks: the proxy client
     * is stripped from the wordpress.org build (see bin/build-release.sh),
     * where this must fall back to the BYO client instead of fataling.
     */
    private function client(): LinkedInClientInterface
    {
        /**
         * Enable the vendor proxy client.
         *
         * @param bool $enabled Default: the license's proxy entitlement.
         */
        if (
            class_exists(ProxyLinkedInClient::class)
            && apply_filters('freshet_feeds_enable_proxy', $this->license->canUseProxy())
        ) {
            return new ProxyLinkedInClient();
        }

        return $this->client;
    }
}
