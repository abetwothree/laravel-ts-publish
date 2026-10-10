<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums;

enum UnsafeIntegerBackedStatus: int
{
    case Huge = 9_007_199_254_740_993;
}
