<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Clash;

/**
 * Named like the composed type `Chart` publishes in this namespace, so both publish the name `ChartType`.
 */
enum ChartType: string
{
    case Bar = 'bar';
}
