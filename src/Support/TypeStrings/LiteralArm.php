<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support\TypeStrings;

/**
 * The scalar a literal union arm spells: a quoted string, a JSON number, `true` or `false`. A name, `null`, a template
 * literal and every structured arm spell none.
 *
 * @internal
 */
final class LiteralArm
{
    /** A number as JSON writes one, the spelling every printer here uses for an int or a float. */
    private const string NUMBER = '/^-?(?:0|[1-9]\d*)(?:\.\d+)?(?:[eE][+-]?\d+)?$/D';

    /**
     * The scalar a literal arm spells, or null for any other arm.
     */
    public static function value(string $arm): string|int|float|bool|null
    {
        return match (true) {
            $arm === 'true' => true,
            $arm === 'false' => false,
            preg_match(self::NUMBER, $arm) === 1 => self::number($arm),
            default => PrintedKey::decodeQuoted($arm),
        };
    }

    /**
     * Whether an arm is a literal that spells a scalar.
     */
    public static function isLiteral(string $arm): bool
    {
        return self::value($arm) !== null;
    }

    /**
     * The int or float a JSON number spells; one past PHP_INT_MAX reads as a float, as JSON reads it.
     */
    private static function number(string $arm): int|float|null
    {
        $number = json_decode($arm);

        return is_int($number) || is_float($number) ? $number : null;
    }
}
