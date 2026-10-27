<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

/** A test-only subclass a resource backed by ViewCountingModel may hold, whose `START` is an int. */
class NinthViewCountingModel extends ViewCountingModel
{
    public const START = 9;
}
