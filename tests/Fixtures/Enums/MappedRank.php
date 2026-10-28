<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums;

use AbeTwoThree\LaravelTsPublish\Attributes\TsEnum;

/**
 * An enum its own file publishes as `Rank`, which a test keys in `custom_ts_mappings`.
 */
#[TsEnum('Rank')]
enum MappedRank: string
{
    case Top = 'top';
    case Bottom = 'bottom';
}
