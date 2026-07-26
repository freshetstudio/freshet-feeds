<?php

declare(strict_types=1);

namespace FreshetFeeds\Item;

use DateTimeImmutable;
use DateTimeZone;

/**
 * One normalized feed item, shared by every provider.
 *
 * Getters return RAW values — templates are responsible for escaping
 * (esc_html / esc_url / wp_kses_post), exactly like WP core template tags' *_raw counterparts.
 */
final readonly class Item
{
    /** @param array<string, mixed> $raw Untouched provider payload — escape hatch for provider-specific data. */
    public function __construct(
        public string $id,
        public string $provider,
        public string $url,
        public DateTimeImmutable $date,
        public string $content = '',
        public ?string $title = null,
        public ?ItemImage $image = null,
        public ?ItemAuthor $author = null,
        public array $raw = [],
    ) {
    }

    public function title(?string $fallback = null): ?string
    {
        return $this->title ?? $fallback;
    }

    /** Formatted, localized via wp_date(). Empty format = site date format. */
    public function date(string $format = ''): string
    {
        if ($format === '') {
            $format = (string) get_option('date_format', 'j F Y');
        }

        return (string) wp_date($format, $this->date->getTimestamp());
    }

    public function datetime(): DateTimeImmutable
    {
        return $this->date;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function excerpt(int $words = 30): string
    {
        return wp_trim_words($this->content, $words);
    }

    public function url(): string
    {
        return $this->url;
    }

    public function hasImage(): bool
    {
        return $this->image !== null && $this->image->url() !== '';
    }

    /** Image URL (local copy preferred), or null. */
    public function image(): ?string
    {
        return $this->hasImage() ? $this->image->url() : null;
    }

    /** @param array<string, string> $attrs Extra HTML attributes (class, loading, sizes, ...). */
    public function imageTag(array $attrs = []): string
    {
        if (!$this->hasImage()) {
            return '';
        }

        $attributes = array_merge([
            'src' => $this->image->url(),
            'alt' => $this->image->alt ?? '',
            'loading' => 'lazy',
            'decoding' => 'async',
        ], $attrs);

        if ($this->image->width !== null && !isset($attrs['width'])) {
            $attributes['width'] = (string) $this->image->width;
        }
        if ($this->image->height !== null && !isset($attrs['height'])) {
            $attributes['height'] = (string) $this->image->height;
        }

        $html = '<img';
        foreach ($attributes as $name => $value) {
            // Values are escaped below; NAMES must be validated — a hostile
            // key like 'x" onerror="…' would otherwise break out of the tag.
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_:.-]*$/', (string) $name)) {
                continue;
            }

            $html .= sprintf(
                ' %s="%s"',
                $name,
                $name === 'src' ? esc_url($value) : esc_attr($value)
            );
        }

        return $html . '>';
    }

    public function author(): ?ItemAuthor
    {
        return $this->author;
    }

    /** @return array<string, mixed> */
    public function raw(): array
    {
        return $this->raw;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'url' => $this->url,
            'date' => $this->date->getTimestamp(),
            'content' => $this->content,
            'title' => $this->title,
            'image' => $this->image?->toArray(),
            'author' => $this->author?->toArray(),
            'raw' => $this->raw,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            provider: (string) ($data['provider'] ?? ''),
            url: (string) ($data['url'] ?? ''),
            date: (new DateTimeImmutable('@' . (int) ($data['date'] ?? 0)))
                ->setTimezone(new DateTimeZone('UTC')),
            content: (string) ($data['content'] ?? ''),
            title: isset($data['title']) ? (string) $data['title'] : null,
            image: is_array($data['image'] ?? null) ? ItemImage::fromArray($data['image']) : null,
            author: is_array($data['author'] ?? null) ? ItemAuthor::fromArray($data['author']) : null,
            raw: is_array($data['raw'] ?? null) ? $data['raw'] : [],
        );
    }

    public function withImage(?ItemImage $image): self
    {
        return new self(
            $this->id,
            $this->provider,
            $this->url,
            $this->date,
            $this->content,
            $this->title,
            $image,
            $this->author,
            $this->raw,
        );
    }
}
