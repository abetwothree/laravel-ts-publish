<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

/** Declares its own stash() and fill(), by value, which neither its parent's body nor its trait reaches. */
final class ByReferenceSelfChild extends ByReferenceSelfBase
{
    /**
     * Takes a copy of the array it is passed.
     *
     * @param  list<mixed>  $into
     */
    public function fill(array $into): void {}

    /**
     * Takes a copy of the array it is passed.
     *
     * @param  list<mixed>  $into
     */
    private static function stash(array $into): void
    {
        $into[] = 1;
    }
}
