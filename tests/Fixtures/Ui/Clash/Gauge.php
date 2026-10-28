<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Clash;

/**
 * A plain class named like an overridden one, which a docblock read against the wrong file would publish instead.
 */
final class Gauge
{
    public int $x = 1;
}
