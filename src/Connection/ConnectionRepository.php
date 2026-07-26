<?php

declare(strict_types=1);

namespace FreshetFeeds\Connection;

/**
 * Connections (non-secret data) in one option; secrets delegated to TokenStore.
 */
final class ConnectionRepository
{
    private const OPTION = 'freshet_feeds_connections';

    public function __construct(private readonly TokenStore $tokens)
    {
    }

    /** @return array<string, LinkedInConnection> Keyed by connection id. */
    public function all(): array
    {
        $raw = get_option(self::OPTION, []);

        if (!is_array($raw)) {
            return [];
        }

        $connections = [];

        foreach ($raw as $data) {
            if (is_array($data)) {
                $connection = LinkedInConnection::fromArray($data);
                $connections[$connection->id] = $connection;
            }
        }

        return $connections;
    }

    public function find(string $id): ?LinkedInConnection
    {
        return $this->all()[$id] ?? null;
    }

    public function save(LinkedInConnection $connection): void
    {
        $all = $this->all();
        $all[$connection->id] = $connection;

        update_option(
            self::OPTION,
            array_values(array_map(
                static fn (LinkedInConnection $c): array => $c->toArray(),
                $all
            )),
            false
        );
    }

    public function delete(string $id): void
    {
        $all = $this->all();
        unset($all[$id]);

        update_option(
            self::OPTION,
            array_values(array_map(
                static fn (LinkedInConnection $c): array => $c->toArray(),
                $all
            )),
            false
        );

        $this->tokens->delete($id);
    }

    public function tokens(): TokenStore
    {
        return $this->tokens;
    }
}
