<?php

declare(strict_types=1);

namespace FreshetFeeds\Item;

final readonly class ItemImage
{
    public function __construct(
        public string $remoteUrl,
        public ?string $localUrl = null,
        public ?string $alt = null,
        public ?int $width = null,
        public ?int $height = null,
    ) {
    }

    /** Local copy when available; providers like LinkedIn serve expiring signed URLs. */
    public function url(): string
    {
        return $this->localUrl ?? $this->remoteUrl;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'remote_url' => $this->remoteUrl,
            'local_url' => $this->localUrl,
            'alt' => $this->alt,
            'width' => $this->width,
            'height' => $this->height,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            remoteUrl: (string) ($data['remote_url'] ?? ''),
            localUrl: isset($data['local_url']) ? (string) $data['local_url'] : null,
            alt: isset($data['alt']) ? (string) $data['alt'] : null,
            width: isset($data['width']) ? (int) $data['width'] : null,
            height: isset($data['height']) ? (int) $data['height'] : null,
        );
    }

    public function withLocalUrl(string $localUrl): self
    {
        return new self($this->remoteUrl, $localUrl, $this->alt, $this->width, $this->height);
    }
}
