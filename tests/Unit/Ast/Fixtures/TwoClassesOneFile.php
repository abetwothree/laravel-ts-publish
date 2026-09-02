<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

class TwoClassesFirst
{
    public function label(): string
    {
        return 'first';
    }
}

class TwoClassesSecond
{
    public function label(): string
    {
        return 'second';
    }
}
