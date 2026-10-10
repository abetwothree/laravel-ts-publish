<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

/** Takes an array by reference in its constructor and a method, for `new` and `parent::` calls to write through. */
class ByReferenceDefaultsParent
{
    /**
     * Appends to the array it is passed.
     *
     * @param  list<mixed>  $into
     */
    public function __construct(array &$into = [])
    {
        $into[] = 1;
    }

    /**
     * Appends to the array it is passed.
     *
     * @param  list<mixed>  $into
     */
    protected function append(array &$into): void
    {
        $into[] = 1;
    }
}
