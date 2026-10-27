<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

/** Declares its own private stash(), by value, which no `self::` in its parent's body or trait reaches. */
final class ByReferenceSelfChild extends ByReferenceSelfBase
{
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
