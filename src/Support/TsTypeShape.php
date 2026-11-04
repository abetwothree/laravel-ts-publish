<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\PrintedKey;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\TypeScanner;

/**
 * Answer structural questions about a TypeScript type string without a full parser.
 *
 * Understands exactly the syntax the metadata pipeline emits: inline object literals and index signatures,
 * Record<K, V>, T[] / readonly T[] / Array<T> / ReadonlyArray<T>, tuples, and top-level unions with null.
 * Intersections and arrow-function types are opaque.
 *
 * Every reader nests and splits as TypeScanner reads the type, so a string or template literal is read whole.
 *
 * @phpstan-type TupleElement = array{string, bool}
 */
final class TsTypeShape
{
    /**
     * Whether every non-null union arm is an object literal, index signature, or Record.
     */
    public static function isObjectLike(string $type): bool
    {
        $arms = self::arms($type);

        return $arms !== [] && array_all($arms, static fn (string $arm): bool => self::armIsObject($arm));
    }

    /**
     * Whether one union arm is a JSON scalar: a number, string or boolean, or a literal of one.
     */
    public static function isScalar(string $arm): bool
    {
        return in_array($arm, ['number', 'string', 'boolean', 'true', 'false'], true)
            || is_numeric($arm)
            || (strlen($arm) >= 2 && in_array($arm[0], ['"', "'"], true) && $arm[-1] === $arm[0]);
    }

    /**
     * Whether an object literal or a `Record<number, …>` sits anywhere in the type, outside literal text.
     */
    public static function holdsKeyedObject(string $type): bool
    {
        $code = TypeScanner::maskLiterals($type);

        return str_contains($code, '{') || preg_match('/\bRecord<\s*number\s*,/', $code) === 1;
    }

    /**
     * The declared type of $key inside an object literal, a Record or a tuple, or null when the type is opaque.
     */
    public static function memberType(string $type, string $key): ?string
    {
        foreach (self::arms($type) as $arm) {
            $record = self::recordArguments($arm);

            if ($record !== null) {
                return $record[1];
            }

            if (self::isWrapped($arm, '[', ']')) {
                $index = PhpArrayKey::listIndex($key);

                return $index === null ? null : self::tupleMembers($arm)[$index] ?? null;
            }

            foreach (self::members($arm) ?? [] as [$name, $value]) {
                // `[key: string]: T` answers every key.
                if (str_starts_with($name, '[') || PrintedKey::names($name, $key)) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Each member type of one tuple, in order, without its optional marker.
     *
     * @return list<string>
     */
    public static function tupleMembers(string $tuple): array
    {
        return array_column(self::tupleElements($tuple), 0);
    }

    /**
     * Each member of one tuple, in order, as its type without its optional marker and whether it carries one.
     *
     * @return list<TupleElement>
     */
    public static function tupleElements(string $tuple): array
    {
        return array_map(self::tupleElement(...), self::splitTopLevel(substr($tuple, 1, -1), [',']));
    }

    /**
     * Whether a member's key, as printed or as written, names a key that passes is_numeric(), a `[key: number]`
     * signature included.
     */
    public static function isNumericKey(string $key): bool
    {
        $key = rtrim($key, '?');

        if (preg_match('/^\[\s*\w+\s*:\s*number\s*\]$/', $key) === 1) {
            return true;
        }

        return is_numeric(PrintedKey::decode($key) ?? $key);
    }

    /**
     * Each member of one object literal as its printed key and its value, or null when the type is not one object
     * literal or a member has no key.
     *
     * @return list<array{string, string}>|null
     */
    public static function members(string $object): ?array
    {
        if (! self::isWrapped($object, '{', '}')) {
            return null;
        }

        $members = [];

        foreach (self::splitTopLevel(substr($object, 1, -1), [';', ',']) as $member) {
            $colon = self::topLevelPosition($member, ':');

            if ($colon === null) {
                return null;
            }

            $members[] = [trim(substr($member, 0, $colon)), trim(substr($member, $colon + 1))];
        }

        return $members;
    }

    /**
     * Whether a type opens with $open and only its last character closes that bracket, as `( … )` does.
     */
    public static function isWrapped(string $type, string $open, string $close): bool
    {
        if (! str_starts_with($type, $open) || ! str_ends_with($type, $close)) {
            return false;
        }

        $last = strlen($type) - 1;
        $outside = array_keys(TypeScanner::depths($type), 0, true);

        return $outside === [0, $last];
    }

    /**
     * The element type of the first union arm that is an array type, or null when no arm is one.
     */
    public static function elementType(string $type): ?string
    {
        foreach (self::arms($type) as $arm) {
            foreach (['Array<', 'ReadonlyArray<'] as $prefix) {
                $generic = substr($arm, strlen($prefix) - 1);
                $arguments = str_starts_with($arm, $prefix) && self::isWrapped($generic, '<', '>')
                    ? self::splitTopLevel(substr($generic, 1, -1), [','])
                    : [];

                if (count($arguments) === 1) {
                    return $arguments[0];
                }
            }

            // `[]` is the empty tuple, not an array suffix, and a tuple has no single element type. `[]` binds tighter
            // than `&` and `=>`, so `A & B[]` lists only `B` and `() => B[]` returns the list.
            $element = $arm !== '[]' && str_ends_with($arm, '[]') ? trim(substr($arm, 0, -2)) : null;
            $listed = $element !== null
                && self::topLevelPosition($element, '&') === null
                && self::topLevelPosition($element, '=') === null;

            if ($listed) {
                return self::isWrapped($element, '(', ')') ? trim(substr($element, 1, -1)) : $element;
            }
        }

        return null;
    }

    /**
     * The key and value types of a type that is one `Record<K, V>`, or null for any other type.
     *
     * @return array{string, string}|null
     */
    public static function recordArguments(string $type): ?array
    {
        if (! str_starts_with($type, 'Record<') || ! self::isWrapped(substr($type, strlen('Record')), '<', '>')) {
            return null;
        }

        $arguments = self::splitTopLevel(substr($type, strlen('Record<'), -1), [',']);

        return count($arguments) === 2 ? [$arguments[0], $arguments[1]] : null;
    }

    /**
     * A list of these element types, each once, or `unknown[]` when one is `unknown`: an element is in every response,
     * so it is never left out as an untypable union arm is.
     *
     * @param  list<string>  $types
     */
    public static function listType(array $types): string
    {
        $types = array_values(array_unique($types));
        $element = match (true) {
            $types === [] => 'never',
            in_array('unknown', $types, true) => 'unknown',
            count($types) === 1 => $types[0],
            default => implode(' | ', array_map(self::unionArm(...), $types)),
        };

        return self::postfixOperand($element).'[]';
    }

    /**
     * One arm of a union as it joins others: parenthesized when it holds a top-level `=>`, whose return type would
     * otherwise take in every arm after it.
     */
    public static function unionArm(string $arm): string
    {
        return self::topLevelPosition($arm, '=') === null ? $arm : '('.$arm.')';
    }

    /**
     * One arm of a union as it joins others, an intersection parenthesized like an arrow, so the union reads as arms.
     */
    public static function groupedArm(string $arm): string
    {
        return count(self::splitTopLevel($arm, ['&'])) > 1 ? '('.$arm.')' : self::unionArm($arm);
    }

    /**
     * A type as the operand of a postfix `[]` or `?`, parenthesized where it holds a top-level `|`, `&` or `=>`: the
     * postfix binds tighter, so `A | B[]` would list only `B`.
     */
    public static function postfixOperand(string $type): string
    {
        return count(self::splitTopLevel($type, ['|', '&'])) > 1 ? '('.$type.')' : self::unionArm($type);
    }

    /**
     * Split on any of $separators that sit at depth zero as TypeScanner reads it: outside brackets, literal text and an
     * arrow's return type.
     *
     * @param  list<string>  $separators  Single characters.
     * @return list<string> Trimmed, non-empty parts.
     */
    public static function splitTopLevel(string $type, array $separators): array
    {
        return TypeScanner::split($type, $separators);
    }

    /**
     * Offset of the first $needle outside brackets, braces, parentheses, angle brackets, and literal text.
     */
    private static function topLevelPosition(string $type, string $needle): ?int
    {
        $offset = strpos($type, $needle);

        if ($offset === false) {
            return null;
        }

        $depths = TypeScanner::depths($type);

        for (; $offset !== false; $offset = strpos($type, $needle, $offset + 1)) {
            if (($depths[$offset] ?? null) === 0) {
                return $offset;
            }
        }

        return null;
    }

    /**
     * Top-level union arms with null and undefined removed and a leading `readonly` stripped.
     *
     * @return list<string>
     */
    private static function arms(string $type): array
    {
        $arms = [];

        foreach (self::splitTopLevel($type, ['|']) as $arm) {
            if ($arm === 'null' || $arm === 'undefined') {
                continue;
            }

            $arms[] = str_starts_with($arm, 'readonly ') ? trim(substr($arm, 9)) : $arm;
        }

        return $arms;
    }

    /**
     * A tuple member's type without its optional marker, unwrapping the parentheses the marker needs after a union,
     * and whether it carries one.
     *
     * @return TupleElement
     */
    private static function tupleElement(string $member): array
    {
        if (! str_ends_with($member, '?')) {
            return [$member, false];
        }

        $member = trim(substr($member, 0, -1));

        return [self::isWrapped($member, '(', ')') ? trim(substr($member, 1, -1)) : $member, true];
    }

    /**
     * Whether one union arm is an object literal or Record.
     */
    private static function armIsObject(string $arm): bool
    {
        return (str_starts_with($arm, '{') && str_ends_with($arm, '}')) || str_starts_with($arm, 'Record<');
    }
}
