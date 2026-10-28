<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Clash;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Concerns\StacksGauges;

/**
 * Uses a trait that brings in the trait documenting `@var list<Gauge>` with the overridden `Gauge` its file imports.
 */
final class GaugeStack
{
    use StacksGauges;
}
