<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

/**
 * Answer structural questions about a TypeScript type string without a full parser.
 *
 * Understands exactly the syntax the metadata pipeline emits: inline object literals and index signatures,
 * Record<K, V>, T[] / readonly T[] / Array<T> / ReadonlyArray<T>, tuples, and top-level unions with null.
 * Intersections and arrow-function types are opaque. Also the home of the one depth-aware top-level splitter.
 */
final class TsTypeShape
{
    /**
     * Whether every non-null union arm is an array type.
     */
    public static function isListLike(string $type): bool
    {
        $arms = self::arms($type);

        return $arms !== [] && array_all($arms, static fn (string $arm): bool => self::armIsList($arm));
    }

    /**
     * Whether every non-null union arm is an object literal, index signature, or Record.
     */
    public static function isObjectLike(string $type): bool
    {
        $arms = self::arms($type);

        return $arms !== [] && array_all($arms, static fn (string $arm): bool => self::armIsObject($arm));
    }

    /**
     * The declared type of $key inside an object literal or Record, or null when the type is opaque.
     */
    public static function memberType(string $type, string $key): ?string
    {
        foreach (self::arms($type) as $arm) {
            if (str_starts_with($arm, 'Record<') && str_ends_with($arm, '>')) {
                $arguments = self::splitTopLevel(substr($arm, 7, -1), [',']);

                return $arguments[1] ?? null;
            }

            if (! str_starts_with($arm, '{') || ! str_ends_with($arm, '}')) {
                continue;
            }

            foreach (self::splitTopLevel(substr($arm, 1, -1), [';', ',']) as $member) {
                $colon = self::topLevelPosition($member, ':');

                if ($colon === null) {
                    continue;
                }

                $name = trim(substr($member, 0, $colon));

                // `[key: string]: T` answers every key; a literal key may be quoted and/or optional.
                if (str_starts_with($name, '[') || trim(rtrim($name, '?'), '\'"') === $key) {
                    return trim(substr($member, $colon + 1));
                }
            }
        }

        return null;
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
     * Split on any of $separators that sit outside brackets, braces, parentheses, angle brackets, and quotes.
     *
     * Depth floors at zero so malformed input fails toward splitting, matching hasTopLevelSeparator().
     *
     * @param  list<string>  $separators  Single characters.
     * @return list<string> Trimmed, non-empty parts.
     */
    public static function splitTopLevel(string $type, array $separators): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $quote = null;

        foreach (str_split($type) as $char) {
            if ($quote !== null) {
                $current .= $char;
                $quote = $char === $quote ? null : $quote;

                continue;
            }

            if ($char === '\'' || $char === '"') {
                $quote = $char;
            } elseif (str_contains('{[(<', $char)) {
                $depth++;
            } elseif (str_contains('}])>', $char)) {
                $depth = max(0, $depth - 1);
            } elseif ($depth === 0 && in_array($char, $separators, true)) {
                $parts[] = $current;
                $current = '';

                continue;
            }

            $current .= $char;
        }

        $parts[] = $current;

        return array_values(array_filter(array_map(trim(...), $parts), static fn (string $part): bool => $part !== ''));
    }

    /**
     * Offset of the first $needle outside brackets, braces, parentheses, angle brackets, and quotes.
     */
    public static function topLevelPosition(string $type, string $needle): ?int
    {
        $depth = 0;
        $quote = null;

        foreach (str_split($type) as $offset => $char) {
            if ($quote !== null) {
                $quote = $char === $quote ? null : $quote;

                continue;
            }

            if ($char === '\'' || $char === '"') {
                $quote = $char;
            } elseif (str_contains('{[(<', $char)) {
                $depth++;
            } elseif (str_contains('}])>', $char)) {
                $depth = max(0, $depth - 1);
            } elseif ($depth === 0 && $char === $needle) {
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
     * Whether one union arm is an array or tuple type.
     */
    private static function armIsList(string $arm): bool
    {
        return str_ends_with($arm, '[]')
            || (str_starts_with($arm, '[') && str_ends_with($arm, ']'))
            || str_starts_with($arm, 'Array<')
            || str_starts_with($arm, 'ReadonlyArray<');
    }

    /**
     * Whether one union arm is an object literal or Record.
     */
    private static function armIsObject(string $arm): bool
    {
        return (str_starts_with($arm, '{') && str_ends_with($arm, '}')) || str_starts_with($arm, 'Record<');
    }
}
