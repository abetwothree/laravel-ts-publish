<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

/**
 * Sorts one untyped default in place; the other is only counted and used as a call's receiver, neither of which passes
 * it by reference. Parsed for its writes, never run.
 */
final class SortedDefaultsSubject
{
    protected $tags = ['a'];

    protected $items = ['a'];

    /** Sorts the tags, and reads the items. */
    public function run(): int
    {
        sort($this->tags);
        $this->items->push(5);

        return count($this->items);
    }
}
