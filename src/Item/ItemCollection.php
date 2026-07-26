<?php

declare(strict_types=1);

namespace FreshetFeeds\Item;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<int, Item>
 */
final class ItemCollection implements IteratorAggregate, Countable
{
    /** @var list<Item> */
    private array $items;

    /** @param iterable<Item> $items */
    public function __construct(iterable $items = [])
    {
        $this->items = array_values(is_array($items) ? $items : iterator_to_array($items));
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function take(int $n): self
    {
        return new self(array_slice($this->items, 0, max(0, $n)));
    }

    /** @return list<Item> */
    public function all(): array
    {
        return $this->items;
    }

    /** @param callable(Item): Item $fn */
    public function map(callable $fn): self
    {
        return new self(array_map($fn, $this->items));
    }

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return array_map(static fn (Item $item): array => $item->toArray(), $this->items);
    }

    /** @param list<array<string, mixed>> $data */
    public static function fromArray(array $data): self
    {
        return new self(array_map(
            static fn (array $item): Item => Item::fromArray($item),
            array_filter($data, 'is_array')
        ));
    }
}
