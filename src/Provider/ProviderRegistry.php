<?php

declare(strict_types=1);

namespace FreshetFeeds\Provider;

final class ProviderRegistry
{
    /** @var array<string, ProviderInterface> */
    private array $providers = [];

    public function register(ProviderInterface $provider): void
    {
        $this->providers[$provider->id()] = $provider;
    }

    public function get(string $id): ?ProviderInterface
    {
        return $this->providers[$id] ?? null;
    }

    /** @return array<string, ProviderInterface> */
    public function all(): array
    {
        return $this->providers;
    }
}
