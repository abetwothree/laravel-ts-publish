<?php

namespace Workbench\App\Enums;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExclude;

/**
 * Entire enum excluded from TypeScript publishing via #[TsExclude].
 */
#[TsExclude]
enum ExcludedEnum: string
{
    case Foo = 'foo';
    case Bar = 'bar';
}
