<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums;

use AbeTwoThree\LaravelTsPublish\Attributes\TsEnum;
use AbeTwoThree\LaravelTsPublish\Attributes\TsType;

/**
 * An enum its own file publishes as `Shade`, with an override for wherever the enum itself is the value.
 */
#[TsEnum('Shade')]
#[TsType(['type' => 'UiToneDto', 'import' => '@js/types/ui'])]
enum RenamedTone: string
{
    case Light = 'light';
    case Dark = 'dark';
}
