<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Generator;

/** Method bodies `ReturnLiteralReader` reads, one return shape per method. */
final class ReturnLiteralProbe
{
    public bool $flag = false;

    /** @var list<string> */
    public array $base = ['id'];

    /** The same literal on both branches. */
    public function sameLiteralTwice(): string
    {
        if ($this->flag) {
            return 'same';
        }

        return 'same';
    }

    /** A generator, whose `return` PHP never hands the caller. */
    public function generatedName(): Generator
    {
        yield 'first';

        return 'generated';
    }

    /** A list of string literals. */
    public function columns(): array
    {
        return ['id', 'uuid'];
    }

    /** The same list on both branches. */
    public function sameColumnsTwice(): array
    {
        if ($this->flag) {
            return ['id'];
        }

        return ['id'];
    }

    /** Lists that differ by branch. */
    public function differentColumns(): array
    {
        if ($this->flag) {
            return ['id'];
        }

        return ['uuid'];
    }

    /** An empty list. */
    public function noColumns(): array
    {
        return [];
    }

    /** A list with a key. */
    public function keyedColumns(): array
    {
        return ['key' => 'id'];
    }

    /** A list spreading a runtime value. */
    public function spreadColumns(): array
    {
        return [...$this->base, 'uuid'];
    }

    /** A list holding a non-string literal. */
    public function mixedColumns(): array
    {
        return ['id', 1];
    }

    /** A list built at runtime. */
    public function computedColumns(): array
    {
        return array_values($this->base);
    }
}
