<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Concerns;

use Workbench\App\Packages\Ui\Gauge;

/**
 * Documents a property it promotes in its constructor with a class its own file imports.
 */
trait PromotesGauges
{
    /**
     * Holds the given gauges.
     *
     * @param  list<Gauge>  $gauges
     */
    public function __construct(
        /** @var list<Gauge> */
        public array $gauges = [],
    ) {}
}
