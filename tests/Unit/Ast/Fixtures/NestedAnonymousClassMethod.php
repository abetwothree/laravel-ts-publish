<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

class NestedAnonymousClassMethod
{
    public function make(): object
    {
        return new class
        {
            public function label(): string
            {
                return 'inner';
            }
        };
    }

    public function label(): string
    {
        return 'outer';
    }
}
