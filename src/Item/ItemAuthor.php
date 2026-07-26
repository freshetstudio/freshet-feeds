<?php

declare(strict_types=1);

namespace FreshetFeeds\Item;

final readonly class ItemAuthor
{
    public function __construct(
        public string $name,
        public ?string $url = null,
        public ?string $imageUrl = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'url' => $this->url,
            'image_url' => $this->imageUrl,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            url: isset($data['url']) ? (string) $data['url'] : null,
            imageUrl: isset($data['image_url']) ? (string) $data['image_url'] : null,
        );
    }
}
