<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums;

/**
 * An enum a test keys in `custom_ts_mappings`, so `toTsType()` answers it with the mapping.
 */
enum MappedLevel: string
{
    case Low = 'low';
    case High = 'high';
}
