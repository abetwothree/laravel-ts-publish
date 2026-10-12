<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\Concerns;

/** A helper a trait supplies, declared only `: array`, so its literal body is the type. */
trait ProvidesNamesakeMeta
{
    public function traitMeta(): array
    {
        return ['c' => true, 'd' => 2];
    }
}
