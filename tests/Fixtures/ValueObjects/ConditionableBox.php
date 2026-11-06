<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Illuminate\Support\Traits\Conditionable;

/** A value object whose when() and unless() are Conditionable's, sent as its one public property. */
final class ConditionableBox
{
    use Conditionable;

    public string $name = 'box';
}
