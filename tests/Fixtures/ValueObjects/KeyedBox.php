<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Illuminate\Support\Traits\Conditionable;

/** A value object whose when() and unless() are Conditionable's, keyed by a string id. */
final class KeyedBox
{
    use Conditionable;

    public string $id = 'n';
}
