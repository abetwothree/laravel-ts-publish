<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Clash;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Concerns\HoldsGauges;

/**
 * Uses a trait whose `@var list<Gauge>` names the overridden `Gauge` its own file imports, not this namespace's.
 */
final class GaugeShelf
{
    use HoldsGauges;

    public int $n = 1;
}
