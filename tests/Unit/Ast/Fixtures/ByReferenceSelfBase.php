<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\Concerns\StashesThroughSelf;

/** Takes an array by reference in its private stash(), which its own body and its trait's reach through `self::`. */
class ByReferenceSelfBase
{
    use StashesThroughSelf;

    public $stashed = ['a'];

    /** Stash the default through this class's stash(), whatever class the object is. */
    public function run(): void
    {
        self::stash($this->stashed);
    }

    /**
     * Appends to the array it is passed.
     *
     * @param  list<mixed>  $into
     */
    private static function stash(array &$into): void
    {
        $into[] = 1;
    }
}
