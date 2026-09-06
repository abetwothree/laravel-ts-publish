<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

class ClassBeforeTraitDecoy
{
    public function label(): string
    {
        return 'decoy';
    }
}

trait ClassBeforeTraitLabel
{
    public function label(): string
    {
        return 'from trait';
    }
}

class UsesClassBeforeTraitLabel
{
    use ClassBeforeTraitLabel;
}
