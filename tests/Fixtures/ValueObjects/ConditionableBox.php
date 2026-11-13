<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Illuminate\Support\Traits\Conditionable;

/** A value object whose when() and unless() are Conditionable's, sent as its one public property. */
final class ConditionableBox
{
    use Conditionable;

    public string $name = 'box';

    /** A label a static callable names. */
    public static function label(self $box, mixed $value): string
    {
        return 'L';
    }

    /** A label a `[$box, 'shout']` pair names. */
    public function shout(self $box, mixed $value): string
    {
        return 'S';
    }

    /** A number a `[$box, 'size']` pair names. */
    public function size(self $box, mixed $value): int
    {
        return 3;
    }
}
