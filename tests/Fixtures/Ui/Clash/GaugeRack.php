<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Clash;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Concerns\HoldsGauges;

/**
 * Redeclares its trait's property with the same docblock, which this file reads as this namespace's `Gauge`.
 */
final class GaugeRack
{
    use HoldsGauges;

    /** @var list<Gauge> */
    public array $gauges = [];
}
