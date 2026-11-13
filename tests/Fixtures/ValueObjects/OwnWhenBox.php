<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Illuminate\Support\Traits\Conditionable;

/** A value object that uses Conditionable but declares its own when(), which returns a count. */
final class OwnWhenBox
{
    use Conditionable;

    public string $name = 'own';

    /** Counts nothing, whatever it is passed. */
    public function when(mixed $value = null, mixed $callback = null): int
    {
        return 1;
    }
}
