<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects;

/**
 * Properties with a get hook, a set hook only, and no hook.
 */
class HookedProperties
{
    public string $plain = '';

    public int $reads {
        get => 1;
    }

    public string $writes {
        set => strtolower($value);
    }
}
