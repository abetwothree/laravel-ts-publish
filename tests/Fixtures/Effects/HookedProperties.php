<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects;

/**
 * Properties with a get hook, a set hook only, no hook, and a get hook an attribute marks.
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

    public int $marked {
        #[HookMarker]
        get => 2;
    }
}
