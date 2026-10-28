<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Concerns;

use Workbench\App\Packages\Ui\Gauge;

/**
 * Documents a property with a class its own file imports, which the namespace of the class using it also declares.
 */
trait HoldsGauges
{
    /** @var list<Gauge> */
    public array $gauges = [];
}
