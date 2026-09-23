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
     * Whether every value $candidate describes is one $type admits, as far as the shapes this class reads can show:
     * unions, primitives and their literals, arrays, string-keyed records and object literals. False when unsure.
     */
    public static function admits(string $type, string $candidate): bool
    {
        $arms = self::unionArms($type);

        return array_all(
            self::unionArms($candidate),
            static fn (string $arm): bool => array_any($arms, static fn (string $typeArm): bool => self::armAdmits($typeArm, $arm)),
        );
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
    private static function topLevelPosition(string $type, string $needle): ?int
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
     * Whether one union arm is an object literal or Record.
     */
    private static function armIsObject(string $arm): bool
    {
        return (str_starts_with($arm, '{') && str_ends_with($arm, '}')) || str_starts_with($arm, 'Record<');
    }

    /**
     * Top-level union arms, null and undefined kept, with an arm that is itself a parenthesized union opened up.
     *
     * @return list<string>
     */
    private static function unionArms(string $type): array
    {
        $arms = [];

        foreach (self::splitTopLevel($type, ['|']) as $arm) {
            $inner = self::isWrapped($arm) ? substr($arm, 1, -1) : null;

            array_push($arms, ...($inner !== null && count(self::splitTopLevel($inner, ['|'])) > 1 ? self::unionArms($inner) : [$arm]));
        }

        return $arms;
    }

    /**
     * Whether a type is one parenthesized group, its first `(` closing at its last character.
     */
    private static function isWrapped(string $type): bool
    {
        if (! str_starts_with($type, '(') || ! str_ends_with($type, ')')) {
            return false;
        }

        $depth = 0;

        foreach (str_split(substr($type, 0, -1)) as $char) {
            $depth += (int) ($char === '(') - (int) ($char === ')');

            if ($depth === 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether one union arm of a type admits one union arm of a candidate.
     */
    private static function armAdmits(string $arm, string $candidate): bool
    {
        if ($arm === $candidate || $arm === 'unknown' || $candidate === 'never' || self::isLiteralOf($candidate, $arm)) {
            return true;
        }

        $element = self::elementType($arm);
        $candidateElement = self::elementType($candidate);

        if ($element !== null || $candidateElement !== null) {
            return $element !== null && $candidateElement !== null && self::admits($element, $candidateElement);
        }

        if (str_starts_with($arm, 'Record<') && str_ends_with($arm, '>')) {
            return self::recordAdmits($arm, $candidate);
        }

        return self::literalAdmits($arm, $candidate);
    }

    /**
     * Whether a `Record<K, V>` arm admits a candidate arm: a record with the same key, or, for string keys, an object
     * literal whose every member V admits.
     */
    private static function recordAdmits(string $record, string $candidate): bool
    {
        $arguments = self::splitTopLevel(substr($record, 7, -1), [',']);
        $key = $arguments[0] ?? '';
        $value = $arguments[1] ?? '';

        if (str_starts_with($candidate, 'Record<') && str_ends_with($candidate, '>')) {
            $candidateArguments = self::splitTopLevel(substr($candidate, 7, -1), [',']);

            return ($candidateArguments[0] ?? null) === $key && self::admits($value, $candidateArguments[1] ?? '');
        }

        $members = $key === 'string' ? self::members($candidate) : null;

        return $members !== null && array_all($members, static fn (array $member): bool => self::admits($value, $member[0]));
    }

    /**
     * Whether an object literal arm admits an object literal candidate: every key it requires is present and required,
     * and every key both name holds a value it admits. A key only the candidate names is an extra the arm allows.
     */
    private static function literalAdmits(string $literal, string $candidate): bool
    {
        $members = self::members($literal);
        $candidateMembers = self::members($candidate);

        if ($members === null || $candidateMembers === null) {
            return false;
        }

        foreach ($members as $name => [$type, $optional]) {
            if (! isset($candidateMembers[$name])) {
                if (! $optional) {
                    return false;
                }

                continue;
            }

            [$candidateType, $candidateOptional] = $candidateMembers[$name];

            if (($candidateOptional && ! $optional) || ! self::admits($type, $candidateType)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The members of an object literal by unquoted name, each with its type and whether it is optional; null for any
     * other type, and for a literal with an index signature, whose keys this reader cannot enumerate.
     *
     * @return array<string, array{string, bool}>|null
     */
    private static function members(string $type): ?array
    {
        if (! str_starts_with($type, '{') || ! str_ends_with($type, '}')) {
            return null;
        }

        $members = [];

        foreach (self::splitTopLevel(substr($type, 1, -1), [';', ',']) as $member) {
            $colon = self::topLevelPosition($member, ':');

            if ($colon === null || str_starts_with($member, '[')) {
                return null;
            }

            $name = trim(substr($member, 0, $colon));
            $members[trim(rtrim($name, '?'), '\'"')] = [trim(substr($member, $colon + 1)), str_ends_with($name, '?')];
        }

        return $members;
    }

    /**
     * Whether a candidate is a string, number or boolean literal of the primitive type.
     */
    private static function isLiteralOf(string $candidate, string $type): bool
    {
        return match ($type) {
            'string' => preg_match('/^([\'"]).*\1$/s', $candidate) === 1,
            'number' => is_numeric($candidate),
            'boolean' => $candidate === 'true' || $candidate === 'false',
            default => false,
        };
    }
}
