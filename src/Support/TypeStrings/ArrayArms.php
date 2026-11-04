<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support\TypeStrings;

use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use Closure;

/**
 * The arms of an array's published type, read one at a time: which arm is an array and which a keyed one, the list
 * `array_values()` makes of an arm, what `array_filter()` keeps of one, and the element of a one-armed list.
 *
 * A stateless reader; Ast\Members\ArrayArm models an arm that a write changes.
 *
 * @internal
 */
final class ArrayArms
{
    /**
     * Each top-level arm of a type through $map, in order, a `null` arm left out where $skipNull; null where $map
     * declines an arm.
     *
     * @param  Closure(string): ?string  $map
     * @return list<string>|null
     */
    public static function arms(string $type, Closure $map, bool $skipNull = true): ?array
    {
        $arms = [];

        foreach (TsTypeShape::splitTopLevel($type, ['|']) as $arm) {
            if ($skipNull && $arm === 'null') {
                continue;
            }

            $mapped = $map($arm);

            if ($mapped === null) {
                return null;
            }

            $arms[] = $mapped;
        }

        return $arms;
    }

    /**
     * Whether one arm is an array: a list, a tuple, or a keyed array.
     */
    public static function isArray(string $arm): bool
    {
        return TsTypeShape::isWrapped($arm, '[', ']') || TsTypeShape::elementType($arm) !== null || self::isKeyed($arm);
    }

    /**
     * Whether one arm is a Record or an object literal, or an intersection of them.
     */
    public static function isKeyed(string $arm): bool
    {
        return array_all(
            TsTypeShape::splitTopLevel($arm, ['&']),
            static fn (string $part): bool => TsTypeShape::members($part) !== null || TsTypeShape::recordArguments($part) !== null,
        );
    }

    /**
     * The list array_values() makes of one arm: a list or tuple as itself, a Record as its values, an object literal
     * as a list of its member types; null for an arm it cannot read.
     */
    public static function valuesOf(string $arm): ?string
    {
        if ($arm === 'never[]' || TsTypeShape::isWrapped($arm, '[', ']')) {
            return $arm;
        }

        $record = TsTypeShape::recordArguments($arm);

        if ($record !== null) {
            return TsTypeShape::listType([$record[1]]);
        }

        $members = TsTypeShape::members($arm);

        if ($members !== null) {
            return TsTypeShape::listType(array_column($members, 1));
        }

        return TsTypeShape::elementType($arm) === null ? null : $arm;
    }

    /**
     * What array_filter() leaves of one arm: a Record as itself, a list as itself or the object its gaps make, each
     * value without its `null` arm where the filter drops nulls; null for any other arm.
     */
    public static function filtered(string $arm, bool $dropsNull): ?string
    {
        $record = TsTypeShape::recordArguments($arm);

        if ($record !== null) {
            return 'Record<'.$record[0].', '.self::filteredValue($record[1], $dropsNull).'>';
        }

        $element = self::listElement($arm);

        if ($element === null) {
            return null;
        }

        $value = self::filteredValue($element, $dropsNull);

        return TsTypeShape::listType([$value]).' | Record<number, '.$value.'>';
    }

    /**
     * The element of a type whose one top-level arm is a list, `T[]`, `(A | B)[]` or `Array<T>`; null for a tuple, a
     * union or any other type.
     */
    public static function listElement(string $type): ?string
    {
        return count(TsTypeShape::splitTopLevel($type, ['|'])) === 1 ? TsTypeShape::elementType($type) : null;
    }

    /**
     * A value array_filter() keeps: without its `null` arm where the filter drops nulls, `never` where null is all.
     */
    private static function filteredValue(string $type, bool $dropsNull): string
    {
        return match (true) {
            ! $dropsNull || ! TsTypeString::hasNullArm($type) => $type,
            $type === 'null' => 'never',
            default => TsTypeString::withoutNullArm($type),
        };
    }
}
