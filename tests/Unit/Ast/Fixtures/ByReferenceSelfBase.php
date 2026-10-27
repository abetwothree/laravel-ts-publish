<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\Concerns\StashesThroughSelf;

/** Takes an array by reference in its private stash() and fill(), which its own body and its trait's reach. */
class ByReferenceSelfBase
{
    use StashesThroughSelf;

    public $stashed = ['a'];

    public $filled = ['a'];

    /** Stash the default through this class's stash(), whatever class the object is. */
    public function run(): void
    {
        self::stash($this->stashed);
    }

    /** Fill the default through this class's private fill(), which PHP calls over a subclass's namesake. */
    public function runFill(): void
    {
        $this->fill($this->filled);
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

    /**
     * Appends to the array it is passed.
     *
     * @param  list<mixed>  $into
     */
    private function fill(array &$into): void
    {
        $into[] = 1;
    }
}
