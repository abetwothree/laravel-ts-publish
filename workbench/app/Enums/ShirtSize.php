<?php

declare(strict_types=1);

namespace Workbench\App\Enums;

use AbeTwoThree\LaravelTsPublish\Attributes\TsEnum;

/** Fixture: a #[TsEnum(name:)] that differs from the class basename, reached from a page prop. */
#[TsEnum(name: 'Size')]
enum ShirtSize: string
{
    case Small = 's';
    case Large = 'l';
}
