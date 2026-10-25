<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCase;

enum PureRevaluedCases
{
    #[TsCase(value: 'shown')]
    case Visible;
    case Hidden;
}
