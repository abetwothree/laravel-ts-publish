<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only plain class, which no filter re-indexes, whose untyped arrays lose or gain elements, or go whole. */
final class AppendedTagsPayload
{
    protected $pruned = ['p'];

    protected $gone = ['g'];

    protected $renumbered = ['a' => 1];

    /** Drop the first pruned entry, then append another. */
    public function prune(): static
    {
        unset($this->pruned[0]);
        $this->pruned[] = 'q';

        return $this;
    }

    /** Key a number in under the key passed. */
    public function renumber(string $key): static
    {
        $this->renumbered[$key] = 2;

        return $this;
    }

    /** Drop the one key the renumbered array starts with. */
    public function dropRenumbered(): static
    {
        unset($this->renumbered['a']);

        return $this;
    }

    /** Unset the whole array. */
    public function forget(): static
    {
        unset($this->gone);

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['pruned' => $this->pruned, 'gone' => $this->gone, 'renumbered' => $this->renumbered];
    }
}
