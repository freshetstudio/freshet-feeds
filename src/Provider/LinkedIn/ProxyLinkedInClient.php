<?php

declare(strict_types=1);

namespace FreshetFeeds\Provider\LinkedIn;

use FreshetFeeds\Feed\Feed;
use FreshetFeeds\Provider\FetchException;

/**
 * Vendor proxy client: calls api.freshet.studio (which holds the approved
 * LinkedIn app) authenticated by the site's license key, so customers skip
 * the LinkedIn developer-app dance entirely.
 *
 * The proxy returns the SAME raw /rest/posts element shape as
 * ByoLinkedInClient, so PostNormalizer and everything downstream are
 * untouched. Image URLs it resolves are signed and expiring — the plugin
 * keeps localising them via ImageStore, never hotlinks.
 *
 * All failures throw FetchException: the provider then serves stale cache
 * (render never blocks; errors never clobber cache). No BYO fallback.
 */
final class ProxyLinkedInClient implements LinkedInClientInterface
{
    private const DEFAULT_BASE_URL = 'https://api.freshet.studio';

    /**
     * Mirrors RemoteLicense::OPTION_KEY as a literal: the License stack is
     * stripped from the wordpress.org build, and referencing the class here
     * would fatal if the filter force-enables the proxy on that build.
     */
    private const LICENSE_KEY_OPTION = 'freshet_feeds_license_key';

    public function getOrganizationPosts(Feed $feed, string $orgUrn, int $count): array
    {
        $data = $this->post('/api/v1/linkedin/posts', [
            'organization' => $orgUrn,
            'count' => min(50, max(1, $count)),
        ]);

        $elements = $data['elements'] ?? null;

        if (!is_array($elements)) {
            throw new FetchException('Freshet Feeds proxy response missing "elements".');
        }

        return array_values(array_filter($elements, 'is_array'));
    }

    public function resolveImages(Feed $feed, array $imageUrns): array
    {
        if ($imageUrns === []) {
            return [];
        }

        $data = $this->post('/api/v1/linkedin/images', ['urns' => array_values($imageUrns)]);

        $images = $data['images'] ?? null;

        if (!is_array($images)) {
            return [];
        }

        $map = [];

        foreach ($images as $urn => $image) {
            if (!is_array($image) || !is_string($image['url'] ?? null)) {
                continue;
            }

            $map[(string) $urn] = [
                'url' => $image['url'],
                'width' => isset($image['width']) ? (int) $image['width'] : null,
                'height' => isset($image['height']) ? (int) $image['height'] : null,
            ];
        }

        return $map;
    }

    /**
     * POST to the proxy service. Same {success, data?, error?, error_code?}
     * envelope as the license server — one parse path, licence-level failures
     * arrive as HTTP 200 with success:false.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed> The envelope's data payload.
     */
    private function post(string $path, array $body): array
    {
        $key = (string) get_option(self::LICENSE_KEY_OPTION, '');

        if ($key === '') {
            throw new FetchException(esc_html__(
                'The managed LinkedIn connection needs an active license — enter your key under Feeds → License.',
                'freshet-feeds'
            ));
        }

        $baseUrl = defined('FRESHET_FEEDS_PROXY_URL')
            ? untrailingslashit((string) FRESHET_FEEDS_PROXY_URL)
            : self::DEFAULT_BASE_URL;

        $response = wp_remote_post($baseUrl . $path, [
            'timeout' => 15,
            'limit_response_size' => 4 * MB_IN_BYTES,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($body + ['key' => $key, 'site_url' => home_url()]),
        ]);

        if (is_wp_error($response)) {
            throw new FetchException(esc_html($response->get_error_message()));
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $decoded = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($decoded) || !isset($decoded['success'])) {
            throw new FetchException(esc_html(
                sprintf('Freshet Feeds proxy returned an unexpected response (HTTP %d).', $status)
            ));
        }

        if (!($decoded['success'] ?? false)) {
            $message = (string) ($decoded['error'] ?? '');

            throw new FetchException(esc_html(sprintf(
                'Freshet Feeds proxy error%s',
                $message !== '' ? ': ' . $message : sprintf(' (HTTP %d)', $status)
            )));
        }

        $data = $decoded['data'] ?? null;

        return is_array($data) ? $data : [];
    }
}
