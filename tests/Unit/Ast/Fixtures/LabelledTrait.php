<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

trait LabelledTrait
{
    public function label(): string
    {
        return 'from trait';
    }
}
