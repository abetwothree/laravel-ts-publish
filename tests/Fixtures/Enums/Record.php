<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums;

/**
 * Named like TypeScript's global `Record`, which a file importing this enum's const still spells bare.
 */
enum Record: string
{
    case Kept = 'kept';
    case Dropped = 'dropped';
}
