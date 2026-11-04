<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\Concerns\PromotesNotes;

/** Takes a promoted property from its trait, and overrides the trait's constructor with one that documents none. */
final class NoteBoard
{
    use PromotesNotes;

    /** Pin the given notes under a limit. */
    public function __construct(public int $limit = 10, string ...$notes)
    {
        $this->notes = $notes;
    }
}
