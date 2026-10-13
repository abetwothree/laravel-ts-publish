<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** Properties, plain and promoted, whose `@var` declares a list shape or a shape with a quoted key. */
final class DeclaredShapeProperties
{
    /** @var list{int, string} */
    public array $pair = [1, 'a'];

    /** @var array{'a': int, b: string} */
    public array $keyed = ['a' => 1, 'b' => 'x'];

    /**
     * Declare the promoted pair and keyed shape.
     *
     * @param  list{int, string}  $promotedPair
     * @param  array{'a': int, b: string}  $promotedKeyed
     */
    public function __construct(
        /** @var list{int, string} */
        public array $promotedPair = [1, 'a'],
        /** @var array{'a': int, b: string} */
        public array $promotedKeyed = ['a' => 1, 'b' => 'x'],
    ) {}
}
