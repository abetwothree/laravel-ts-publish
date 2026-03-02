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

    /** Should NOT be published — no TsEnumMethod attribute */
    public function formatAmount(float $amount): string
    {
        $symbol = self::symbols()[$this->value] ?? '$';

        return $symbol.number_format($amount, $this === self::Jpy ? 0 : 2);
    }

    /** Should NOT be published — no TsEnumStaticMethod attribute */
    public static function supportedCodes(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
