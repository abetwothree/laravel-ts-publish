<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums;

/**
 * Named like a composed class, so the `ChartType` it publishes is the name that class's composed type takes.
 */
enum Chart: string
{
    case Bar = 'bar';
    case Line = 'line';
}
