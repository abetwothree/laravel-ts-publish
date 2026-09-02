<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

class ParentWithLabelMethod
{
    public function label(): string
    {
        return 'from parent';
    }
}

trait TraitOverridingParentLabel
{
    public function label(): string
    {
        return 'from trait override';
    }
}

class ChildOverridesParentWithTrait extends ParentWithLabelMethod
{
    use TraitOverridingParentLabel;
}
