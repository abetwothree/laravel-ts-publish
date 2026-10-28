<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Clash;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Concerns\PromotesGauges;

/**
 * Uses a trait whose constructor promotes a documented property naming the overridden `Gauge`.
 */
final class GaugeBox
{
    use PromotesGauges;
}
