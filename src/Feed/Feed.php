<?php

declare(strict_types=1);

namespace FreshetFeeds\Feed;

final readonly class Feed
{
    public const DEFAULT_TTL = HOUR_IN_SECONDS;
    public const DEFAULT_COUNT = 10;

    /**
     * @param array<string, mixed> $settings Provider-specific settings (e.g. LinkedIn: organization_id, connection_id).
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public string $providerId,
        public array $settings = [],
        public int $count = self::DEFAULT_COUNT,
        public int $ttl = self::DEFAULT_TTL,
        public string $defaultLayout = 'grid',
    ) {
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    public function with(array $changes): self
    {
        return new self(
            id: $changes['id'] ?? $this->id,
            name: $changes['name'] ?? $this->name,
            slug: $changes['slug'] ?? $this->slug,
            providerId: $changes['providerId'] ?? $this->providerId,
            settings: $changes['settings'] ?? $this->settings,
            count: $changes['count'] ?? $this->count,
            ttl: $changes['ttl'] ?? $this->ttl,
            defaultLayout: $changes['defaultLayout'] ?? $this->defaultLayout,
        );
    }
}
