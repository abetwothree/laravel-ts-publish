<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCase;

enum BackedRevaluedCases: string
{
    #[TsCase(value: 'shown')]
    case Visible = 'visible';
    case Hidden = 'hidden';
}
