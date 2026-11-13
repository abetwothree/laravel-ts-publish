<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support\TypeStrings;

use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use Closure;

/**
 * The arms of an array's published type, read one at a time: which arm is an array or a keyed one, the list each
 * values() spelling makes, its keys, what `array_filter()` and `array_map()` make of an arm and the value each element
 * holds, the two spellings of a list whose keys left 0..n-1, and the element of a one-armed list.
 *
 * A stateless reader; Ast\Members\ArrayArm models an arm that a write changes.
 *
 * @internal
 */
final class ArrayArms
{
    /** The largest integer a JSON number carries exactly to JavaScript. */
    private const int MAX_SAFE_INTEGER = 9_007_199_254_740_991;

    /**
     * Each top-level arm of a type through $map, in order, a `null` arm left out where $skipNull; null where $map
     * declines an arm. A gap arm, which no rule typed, is no arm to read: a caller that builds a type gives it back.
     *
     * @param  Closure(string): ?string  $map
     * @return list<string>|null
     */
    public static function arms(string $type, Closure $map, bool $skipNull = true): ?array
    {
        $arms = [];
        $type = VisibleGaps::aside($type)[0];

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

        $members = VisibleGaps::members($arm)[0] ?? null;

        if ($members !== null) {
            return TsTypeShape::listType(array_column($members, 1));
        }

        return TsTypeShape::elementType($arm) === null ? null : $arm;
    }

    /**
     * The list `values()` re-indexes a collection of this type into, each arm as valuesOf() lists it, an empty arm left
     * out beside another and a short-circuit `null` kept; null where an arm cannot be read.
     */
    public static function valuesList(string $type): ?string
    {
        return VisibleGaps::carried($type, static function (string $type): ?string {
            $lists = self::arms($type, static fn (string $arm): ?string => $arm === 'null' ? $arm : self::valuesOf($arm), skipNull: false);

            if ($lists === null || $lists === []) {
                return null;
            }

            $filled = array_values(array_diff($lists, ['never[]', 'null']));

            return TsTypeString::hoistNull($filled === [] ? $lists : array_values(array_diff($lists, ['never[]'])));
        });
    }

    /**
     * The keys of one arm as PHP holds them: a tuple's numbers, a list's where $listed says it is keyed by ints, a
     * Record's `string` or `number` keys, `never` for none. Null otherwise: a list may be PHPDoc's `T[]`, and an object
     * literal or a `Record<string, unknown>` a literal printed without the keys it cannot name.
     */
    public static function keysOf(string $arm, bool $listed = false): ?string
    {
        if ($arm === 'never[]' || $arm === '[]') {
            return 'never';
        }

        $record = TsTypeShape::recordArguments($arm);

        if ($record !== null) {
            $named = array_all(TsTypeShape::splitTopLevel($record[0], ['|']), static fn (string $key): bool => in_array($key, ['string', 'number'], true));

            return $named && $arm !== 'Record<string, unknown>' ? $record[0] : null;
        }

        return TsTypeShape::isWrapped($arm, '[', ']') || ($listed && TsTypeShape::elementType($arm) !== null) ? 'number' : null;
    }

    /**
     * The list `array_keys()` and `keys()` make of a type, each arm's keys as keysOf() reads them, a `null` arm left
     * out; null where no other arm is left, or one cannot be read.
     */
    public static function keysList(string $type, bool $listed = false): ?string
    {
        return VisibleGaps::carried($type, static function (string $type) use ($listed): ?string {
            $keys = self::arms($type, static fn (string $arm): ?string => self::keysOf($arm, $listed));

            return $keys === null || $keys === [] ? null : TsTypeShape::listType(array_values(array_diff($keys, ['never'])));
        });
    }

    /**
     * Whether any top-level arm of a type is a list, `T[]` or `Array<T>`, whose keys its spelling leaves open.
     */
    public static function holdsList(string $type): bool
    {
        return array_any(TsTypeShape::splitTopLevel($type, ['|']), static fn (string $arm): bool => self::listElement($arm) !== null);
    }

    /**
     * The list `array_keys()` makes of the keys an array holds: `number[]` for keys 0..n-1, `never[]` for none, else
     * each key's literal, or `number` for an int past what a JSON number carries exactly.
     *
     * @param  list<int|string>  $keys
     */
    public static function keysListOf(array $keys): string
    {
        if ($keys !== [] && $keys === range(0, count($keys) - 1)) {
            return 'number[]';
        }

        $literals = array_map(
            static fn (int|string $key): string => is_int($key) && abs($key) > self::MAX_SAFE_INTEGER ? 'number' : JsEmitter::toJsLiteral($key),
            $keys,
        );

        return TsTypeShape::listType($literals === [] ? [] : [implode(' | ', array_values(array_unique($literals)))]);
    }

    /**
     * The value each element of one arm holds: a list's element, a Record's value, a tuple's or an object literal's
     * members joined, `never` for no element, and `unknown` for an arm nothing types; null for any other arm.
     */
    public static function elementOf(string $arm): ?string
    {
        if ($arm === 'unknown') {
            return $arm;
        }

        $values = $arm === '[]' ? 'never[]' : self::valuesOf($arm);

        if ($values !== null && TsTypeShape::isWrapped($values, '[', ']')) {
            $values = TsTypeShape::listType(TsTypeShape::tupleMembers($values));
        }

        return $values === null ? null : self::listElement($values);
    }

    /**
     * What array_map() makes of one arm, each element's value replaced by $value under the key it had: a list or tuple
     * as a list, a Record and an object literal keyed as they were, and an arm nothing types as either a list or a
     * Record; null for any other arm.
     */
    public static function mapped(string $arm, string $value): ?string
    {
        $list = TsTypeShape::listType([$value]);
        $record = TsTypeShape::recordArguments($arm);
        [$members, $open] = VisibleGaps::members($arm) ?? [null, false];

        return match (true) {
            $arm === 'unknown' => $list.' | Record<string, '.$value.'>',
            $record !== null => 'Record<'.$record[0].', '.$value.'>',
            // json_encode() writes an empty array as `[]`.
            $members === [] => 'never[]',
            $members !== null => VisibleGaps::opened(
                '{ '.implode('; ', array_map(static fn (array $member): string => $member[0].': '.$value, $members)).' }',
                $open,
            ),
            TsTypeShape::isWrapped($arm, '[', ']') || TsTypeShape::elementType($arm) !== null => $list,
            default => null,
        };
    }

    /**
     * What array_filter() leaves of one arm: a Record as itself, a list as itself or the object its gaps make, an
     * object literal as filteredObject() reads it, each value without its `null` arm where the filter drops nulls;
     * null for any other arm.
     */
    public static function filtered(string $arm, bool $dropsNull): ?string
    {
        $record = TsTypeShape::recordArguments($arm);

        if ($record !== null) {
            return 'Record<'.$record[0].', '.self::filteredValue($record[1], $dropsNull).'>';
        }

        [$members, $open] = VisibleGaps::members($arm) ?? [null, false];

        if ($members !== null) {
            $object = self::filteredObject($members, $dropsNull);

            return $object === null ? null : VisibleGaps::opened($object, $open);
        }

        $element = self::listElement($arm);

        if ($element === null) {
            return null;
        }

        return self::gappedList(self::filteredValue($element, $dropsNull));
    }

    /**
     * The arms json_encode() writes a PHP list array_filter() may leave gaps in as, its keys spelled as numbers: `X[]`
     * or `Record<number, X>`.
     */
    public static function gappedList(string $value): string
    {
        return TsTypeShape::listType([$value]).' | Record<number, '.$value.'>';
    }

    /**
     * The arms json_encode() writes a collection list whose keys a step left out of 0..n-1 as, its keys spelled as
     * strings: `X[]` → `X[] | Record<string, X>`.
     */
    public static function keyedList(string $list): string
    {
        return (string) VisibleGaps::carried($list, static fn (string $list): string => $list.' | Record<string, '.substr($list, 0, -2).'>');
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
     * What array_filter() leaves of an object literal: each member optional, as any may be dropped, a null-only one
     * left out where nulls are, and `never[]` for none left; null where the keys it leaves may re-index into a list.
     *
     * @param  list<array{string, string}>  $members
     */
    private static function filteredObject(array $members, bool $dropsNull): ?string
    {
        $keys = array_map(PrintedKey::decode(...), array_column($members, 0));
        $numeric = array_filter($keys, is_numeric(...));

        // An API resource re-indexes the int keys left once no named one is, and json_encode() lists keys 0..n-1.
        if (in_array(null, $keys, true) || ($numeric !== [] && count($numeric) < count($keys)) || in_array('0', $keys, true)) {
            return null;
        }

        $kept = [];

        foreach ($members as [$key, $value]) {
            $value = self::filteredValue($value, $dropsNull);

            if ($value !== 'never') {
                $kept[] = (str_ends_with($key, '?') ? rtrim(substr($key, 0, -1)) : $key).'?: '.$value;
            }
        }

        return $kept === [] ? 'never[]' : '{ '.implode('; ', $kept).' }';
    }

    /**
     * A value array_filter() keeps: without its `null` arm where the filter drops nulls, `never` where null is all.
     */
    private static function filteredValue(string $type, bool $dropsNull): string
    {
        return match (true) {
            ! $dropsNull || ! TsTypeString::hasNullArm($type) => $type,
            VisibleGaps::aside($type)[0] === 'null' => 'never',
            default => TsTypeString::withoutNullArm($type),
        };
    }
}
