<?php

declare(strict_types=1);

namespace FreshetFeeds\Connection;

/**
 * A configured LinkedIn connection. Client secret and tokens are NOT here —
 * they live in TokenStore; this struct is safe to list in admin screens.
 */
final readonly class LinkedInConnection
{
    public const MODE_BYO = 'byo';
    public const MODE_PROXY = 'proxy';

    public function __construct(
        public string $id,
        public string $label,
        public string $mode,
        public string $clientId = '',
        public int $tokenExpiresAt = 0,
        public int $refreshTokenExpiresAt = 0,
        public bool $needsReauth = false,
    ) {
    }

    public function isConnected(): bool
    {
        return !$this->needsReauth && $this->tokenExpiresAt > time();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'mode' => $this->mode,
            'client_id' => $this->clientId,
            'token_expires_at' => $this->tokenExpiresAt,
            'refresh_token_expires_at' => $this->refreshTokenExpiresAt,
            'needs_reauth' => $this->needsReauth,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            label: (string) ($data['label'] ?? ''),
            mode: (string) ($data['mode'] ?? self::MODE_BYO),
            clientId: (string) ($data['client_id'] ?? ''),
            tokenExpiresAt: (int) ($data['token_expires_at'] ?? 0),
            refreshTokenExpiresAt: (int) ($data['refresh_token_expires_at'] ?? 0),
            needsReauth: (bool) ($data['needs_reauth'] ?? false),
        );
    }

    public function with(array $changes): self
    {
        return self::fromArray(array_merge($this->toArray(), $changes));
    }
}
