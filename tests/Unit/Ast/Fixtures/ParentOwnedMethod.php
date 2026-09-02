<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

class ParentOwnedMethod
{
    public function label(): string
    {
        return 'from parent';
    }
}
