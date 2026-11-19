<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects;

/**
 * A flag and a label whose hooks an implementer may give.
 */
interface FlagContract
{
    public bool $flag { get; }

    public string $label { get; set; }
}
