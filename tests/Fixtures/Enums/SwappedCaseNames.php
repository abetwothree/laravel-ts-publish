<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCase;
use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumMethod;

/**
 * Two cases renamed to each other's PHP names, each with its own instance-method value.
 */
enum SwappedCaseNames: string
{
    #[TsCase(name: 'Second')]
    case First = 'first';

    #[TsCase(name: 'First')]
    case Second = 'second';

    /**
     * Label the case by its PHP name.
     */
    #[TsEnumMethod]
    public function label(): string
    {
        return match ($this) {
            self::First => 'first case',
            self::Second => 'second case',
        };
    }
}
