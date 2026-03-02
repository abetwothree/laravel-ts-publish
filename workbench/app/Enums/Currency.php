<?php

namespace Workbench\App\Enums;

use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumStaticMethod;

/**
 * String-backed enum with static methods that return complex array structures.
 */
enum Currency: string
{
    case Usd = 'USD';
    case Eur = 'EUR';
    case Gbp = 'GBP';
    case Jpy = 'JPY';
    case Cad = 'CAD';

    #[TsEnumStaticMethod(description: 'Get all currency symbols as a map')]
    public static function symbols(): array
    {
        return [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'CAD' => 'C$',
        ];
    }

    #[TsEnumStaticMethod(description: 'Get the default currency code')]
    public static function default(): string
    {
        return self::Usd->value;
    }

    #[TsEnumStaticMethod(description: 'Get detailed info for all currencies')]
    public static function details(): array
    {
        return array_map(fn (self $currency) => [
            'code' => $currency->value,
            'symbol' => self::symbols()[$currency->value],
            'decimals' => $currency === self::Jpy ? 0 : 2,
        ], self::cases());
    }
}
