<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

trait InsteadofTraitA
{
    public function label(): string
    {
        return 'from A';
    }
}

trait InsteadofTraitB
{
    public function label(): string
    {
        return 'from B';
    }
}

class UsesInsteadofTraits
{
    use InsteadofTraitA, InsteadofTraitB {
        InsteadofTraitA::label insteadof InsteadofTraitB;
    }
}
