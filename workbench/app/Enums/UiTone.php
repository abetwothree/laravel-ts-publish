<?php

declare(strict_types=1);

namespace Workbench\App\Enums;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;

/**
 * Fixture: an enum with an override, which a value of the enum publishes, while its own file, an `EnumResource`, its
 * `->value` and its `->name` keep the enum's names.
 */
#[TsType(['type' => 'UiToneDto', 'import' => '@js/types/ui'])]
enum UiTone: string
{
    case Bright = 'bright';
    case Dim = 'dim';
}
