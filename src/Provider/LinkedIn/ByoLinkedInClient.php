<?php

declare(strict_types=1);

namespace FreshetFeeds\Provider\LinkedIn;

use FreshetFeeds\Auth\LinkedInOAuth;
use FreshetFeeds\Connection\ConnectionRepository;
use FreshetFeeds\Connection\LinkedInConnection;
use FreshetFeeds\Feed\Feed;
use FreshetFeeds\Provider\FetchException;
use Throwable;

/**
 * Live LinkedIn REST client using the site owner's own developer app
 * (Community Management API). Retries once after a token refresh on 401.
 */
final class ByoLinkedInClient implements LinkedInClientInterface
{
    private const API_BASE = 'https://api.linkedin.com/rest';

    /**
     * LinkedIn sunsets API versions; bump per release, filterable as an escape
     * hatch via `freshet_feeds_linkedin_version`.
     */
    public const API_VERSION = '202506';

    public function __construct(private readonly ConnectionRepository $connections)
    {
    }

    public function getOrganizationPosts(Feed $feed, string $orgUrn, int $count): array
    {
        $data = $this->request($feed, add_query_arg([
            'q' => 'author',
            'author' => rawurlencode($orgUrn),
            'count' => min(50, max(1, $count)),
        ], self::API_BASE . '/posts'));

        $elements = $data['elements'] ?? null;

        if (!is_array($elements)) {
            throw new FetchException('LinkedIn posts response missing "elements".');
        }

        return array_values(array_filter($elements, 'is_array'));
    }

    public function resolveImages(Feed $feed, array $imageUrns): array
    {
        if ($imageUrns === []) {
            return [];
        }

        // Batch get: /rest/images?ids=List(urn1,urn2) — URNs must be individually encoded.
        $ids = 'List(' . implode(',', array_map('rawurlencode', $imageUrns)) . ')';
        $data = $this->request($feed, self::API_BASE . '/images?ids=' . $ids);

        $results = $data['results'] ?? null;

        if (!is_array($results)) {
            return [];
        }

        $map = [];

        foreach ($results as $urn => $image) {
            if (!is_array($image) || !is_string($image['downloadUrl'] ?? null)) {
                continue;
            }

            $map[(string) $urn] = [
                'url' => $image['downloadUrl'],
                'width' => isset($image['displaySize']['width']) ? (int) $image['displaySize']['width'] : null,
                'height' => isset($image['displaySize']['height']) ? (int) $image['displaySize']['height'] : null,
            ];
        }

        return $map;
    }

    /** @return array<string, mixed> */
    private function request(Feed $feed, string $url, bool $isRetry = false): array
    {
        $connection = $this->connection($feed);
        $secrets = $this->connections->tokens()->get($connection->id);
        $accessToken = (string) ($secrets['access_token'] ?? '');

        if ($accessToken === '') {
            throw new FetchException(esc_html(sprintf(
                'LinkedIn connection "%s" has no access token — connect it first.',
                $connection->label
            )));
        }

        /** This filter exists because LinkedIn sunsets API versions. */
        $version = (string) apply_filters('freshet_feeds_linkedin_version', self::API_VERSION);

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'limit_response_size' => 4 * MB_IN_BYTES,
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'LinkedIn-Version' => $version,
                'X-Restli-Protocol-Version' => '2.0.0',
            ],
        ]);

        if (is_wp_error($response)) {
            throw new FetchException(esc_html($response->get_error_message()));
        }

        $status = (int) wp_remote_retrieve_response_code($response);

        if ($status === 401 && !$isRetry) {
            $this->refreshOrFlag($connection);

            return $this->request($feed, $url, true);
        }

        if ($status < 200 || $status >= 300) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $message = is_array($body) ? (string) ($body['message'] ?? '') : '';

            throw new FetchException(esc_html(sprintf(
                'LinkedIn API error (HTTP %d)%s',
                $status,
                $message !== '' ? ': ' . $message : ''
            )));
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($data)) {
            throw new FetchException('LinkedIn API returned invalid JSON.');
        }

        return $data;
    }

    private function connection(Feed $feed): LinkedInConnection
    {
        $connectionId = (string) $feed->setting('connection_id', '');
        $connection = $this->connections->find($connectionId);

        if ($connection === null) {
            throw new FetchException('Feed has no LinkedIn connection configured.');
        }

        return $connection;
    }

    private function refreshOrFlag(LinkedInConnection $connection): void
    {
        try {
            (new LinkedInOAuth($this->connections))->refresh($connection);
        } catch (Throwable $e) {
            $this->connections->save($connection->with(['needs_reauth' => true]));

            throw new FetchException(esc_html(sprintf(
                'LinkedIn token expired and refresh failed (%s) — reconnect "%s" under Feeds → Connections.',
                $e->getMessage(),
                $connection->label
            )));
        }
    }
}
