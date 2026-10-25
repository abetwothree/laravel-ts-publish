<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

/**
 * Answer structural questions about a TypeScript type string without a full parser.
 *
 * Understands exactly the syntax the metadata pipeline emits: inline object literals and index signatures,
 * Record<K, V>, T[] / readonly T[] / Array<T> / ReadonlyArray<T>, tuples, and top-level unions with null.
 * Intersections and arrow-function types are opaque. Also the home of the one top-level splitter for
 * TypeScript type strings; PHPDoc types keep their own in LaravelTsPublish::splitPhpDocUnionType().
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
     * Whether an object literal or a `Record<number, …>` sits anywhere in the type, outside quotes.
     *
     * A template literal's `${…}` reads as an object, so an unsure answer is yes.
     */
    public static function holdsKeyedObject(string $type): bool
    {
        $unquoted = preg_replace('/([\'"])(?:\\\\.|(?!\1).)*\1/s', '', $type) ?? $type;

        return str_contains($unquoted, '{') || preg_match('/\bRecord<\s*number\s*,/', $unquoted) === 1;
    }

    /**
     * The declared type of $key inside an object literal, a Record or a tuple, or null when the type is opaque.
     */
    public static function memberType(string $type, string $key): ?string
    {
        foreach (self::arms($type) as $arm) {
            if (str_starts_with($arm, 'Record<') && str_ends_with($arm, '>')) {
                $arguments = self::splitTopLevel(substr($arm, 7, -1), [',']);

                return $arguments[1] ?? null;
            }

            if (self::isWrapped($arm, '[', ']')) {
                return preg_match('/^(?:0|[1-9]\d*)$/', $key) === 1
                    ? self::tupleMember(self::splitTopLevel(substr($arm, 1, -1), [','])[(int) $key] ?? null)
                    : null;
            }

            foreach (self::members($arm) ?? [] as [$name, $value]) {
                // `[key: string]: T` answers every key; a literal key may be quoted and/or optional.
                if (str_starts_with($name, '[') || trim(rtrim($name, '?'), '\'"') === $key) {
                    return $value;
                }
            }
        }

        return null;
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

        $unquoted = preg_match('/^([\'"])(.*)\1$/s', $key, $match) === 1 ? stripcslashes($match[2]) : $key;

        return is_numeric($unquoted);
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
        $outside = array_keys(self::depths($type), 0, true);

        return $outside === [0, $last];
    }

    /**
     * The element type of an array type, or null when the type is not an array.
     */
    public static function elementType(string $type): ?string
    {
        foreach (self::arms($type) as $arm) {
            foreach (['Array<', 'ReadonlyArray<'] as $prefix) {
                if (str_starts_with($arm, $prefix) && str_ends_with($arm, '>')) {
                    return trim(substr($arm, strlen($prefix), -1));
                }
            }

            // `[]` is the empty tuple, not an array suffix, and a tuple has no single element type.
            if ($arm !== '[]' && str_ends_with($arm, '[]')) {
                $element = trim(substr($arm, 0, -2));

                return str_starts_with($element, '(') && str_ends_with($element, ')')
                    ? trim(substr($element, 1, -1))
                    : $element;
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
            default => implode(' | ', $types),
        };

        // `[]` binds tighter than `|`, `&` and `=>`, so `A | B[]` would list only B.
        return count(self::splitTopLevel($element, ['|', '&'])) > 1 || self::topLevelPosition($element, '=') !== null
            ? '('.$element.')[]'
            : $element.'[]';
    }

    /**
     * Split on any of $separators that sit outside brackets, braces, parentheses, angle brackets, and quotes.
     *
     * @param  list<string>  $separators  Single characters.
     * @return list<string> Trimmed, non-empty parts.
     */
    public static function splitTopLevel(string $type, array $separators): array
    {
        $parts = [];
        $start = 0;

        foreach (self::depths($type) as $offset => $depth) {
            if ($depth === 0 && in_array($type[$offset], $separators, true)) {
                $parts[] = substr($type, $start, $offset - $start);
                $start = $offset + 1;
            }
        }

        $parts[] = substr($type, $start);

        return array_values(array_filter(array_map(trim(...), $parts), static fn (string $part): bool => $part !== ''));
    }

    /**
     * Offset of the first $needle outside brackets, braces, parentheses, angle brackets, and quotes.
     */
    private static function topLevelPosition(string $type, string $needle): ?int
    {
        foreach (self::depths($type) as $offset => $depth) {
            if ($depth === 0 && $type[$offset] === $needle) {
                return $offset;
            }
        }

        return null;
    }

    /**
     * The bracket depth of each character outside quotes, by offset; a bracket sits at the depth outside the pair it
     * opens or closes.
     *
     * Depth floors at zero so malformed input fails toward splitting, matching hasTopLevelSeparator(), and the `>` of
     * `=>` closes nothing.
     *
     * @return array<int, int>
     */
    private static function depths(string $type): array
    {
        $depths = [];
        $depth = 0;
        $quote = null;

        foreach (str_split($type) as $offset => $char) {
            if ($quote !== null) {
                $quote = $char === $quote ? null : $quote;
            } elseif ($char === '\'' || $char === '"') {
                $quote = $char;
            } elseif (str_contains('{[(<', $char)) {
                $depths[$offset] = $depth++;
            } elseif (str_contains('}])>', $char) && ! ($char === '>' && $offset > 0 && $type[$offset - 1] === '=')) {
                $depths[$offset] = $depth = max(0, $depth - 1);
            } else {
                $depths[$offset] = $depth;
            }
        }

        return $depths;
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
     * A tuple member's type without its optional marker, unwrapping the parentheses the marker needs after a union.
     */
    private static function tupleMember(?string $member): ?string
    {
        if ($member === null || ! str_ends_with($member, '?')) {
            return $member;
        }

        $member = trim(substr($member, 0, -1));

        return self::isWrapped($member, '(', ')') ? trim(substr($member, 1, -1)) : $member;
    }

    /**
     * Whether one union arm is an object literal or Record.
     */
    private static function armIsObject(string $arm): bool
    {
        return (str_starts_with($arm, '{') && str_ends_with($arm, '}')) || str_starts_with($arm, 'Record<');
    }
}
