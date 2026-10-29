<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCase;

enum PureRenamedCases
{
    #[TsCase(name: 'Shown')]
    case Visible;
    case Hidden;
}
