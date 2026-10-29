<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only plain class, which no filter re-indexes, whose untyped arrays lose an element or are unset whole. */
final class AppendedTagsPayload
{
    protected $pruned = ['p'];

    protected $gone = ['g'];

    /** Drop the first pruned entry, then append another. */
    public function prune(): static
    {
        unset($this->pruned[0]);
        $this->pruned[] = 'q';

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
        return ['pruned' => $this->pruned, 'gone' => $this->gone];
    }
}
