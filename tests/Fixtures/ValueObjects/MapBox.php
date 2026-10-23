<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/**
 * A hand-written bag, not a collection, with its own map(), values() and all(): its items keep string keys.
 */
final class MapBox
{
    /** @param array<string, mixed> $items */
    public function __construct(public array $items = []) {}

    /** Maps each item, keeping its key. */
    public function map(callable $callback): self
    {
        $mapped = [];

        foreach ($this->items as $key => $item) {
            $mapped[$key] = $callback($item);
        }

        return new self($mapped);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->items;
    }

    /** @return list<mixed> */
    public function values(): array
    {
        return array_values($this->items);
    }
}
