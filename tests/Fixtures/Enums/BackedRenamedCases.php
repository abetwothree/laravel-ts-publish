<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCase;

enum BackedRenamedCases: string
{
    #[TsCase(name: 'Shown')]
    case Visible = 'visible';
    case Hidden = 'hidden';
}
