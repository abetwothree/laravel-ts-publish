<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

/**
 * Answers whether an API resource response re-indexes an array into a list, for a PHP value or a published type.
 *
 * `ConditionallyLoadsAttributes::filter()` recurses into arrays only, never into an object, and
 * `removeMissingValues()` re-indexes an array whose keys all pass `is_numeric()`, unless the resource preserves keys.
 *
 * @internal
 */
final class ResourceReindexing
{
    private const string NUMBER_RECORD = 'Record<number, ';

    private const string STRING_RECORD = 'Record<string, ';

    /**
     * Whether a resource re-indexes an array holding exactly these keys into a list.
     *
     * @param  list<array-key>  $keys
     */
    public static function reindexesKeys(array $keys): bool
    {
        return $keys !== [] && array_all($keys, static fn (int|string $key): bool => is_numeric($key));
    }

    /**
     * Whether the array, or one an array holds at any depth, has only numeric keys yet is not a list.
     *
     * @param  array<array-key, mixed>  $value
     */
    public static function reindexesAsList(array $value): bool
    {
        if (! array_is_list($value) && self::reindexesKeys(array_keys($value))) {
            return true;
        }

        return array_any($value, fn (mixed $item): bool => is_array($item) && self::reindexesAsList($item));
    }

    /**
     * The type a resource sends a value of this type as: an object literal, or an intersection of them and
     * `Record<number, T>`, whose every key passes is_numeric() becomes a list of its values, at any depth.
     *
     * A named type is opaque: a nested resource, model or enum serializes itself, and the filter never enters it.
     */
    public static function reindexedType(string $type): string
    {
        $arms = TsTypeShape::splitTopLevel($type, ['|']);

        if (count($arms) > 1) {
            $rewritten = array_map(self::reindexedType(...), $arms);

            // Two arrays a resource lists alike are one arm once re-indexed.
            return $rewritten === $arms ? $type : implode(' | ', array_unique($rewritten));
        }

        $parts = TsTypeShape::splitTopLevel($type, ['&']);

        if (count($parts) > 1) {
            $values = self::numericKeyedValues($parts);

            if ($values !== null) {
                return TsTypeShape::listType($values);
            }

            // A named key keeps every part's keys, so only the values inside each part can be re-indexed.
            $rewritten = array_map(self::reindexedMembers(...), $parts);

            return $rewritten === $parts ? $type : implode(' & ', $rewritten);
        }

        return self::reindexedArm(trim($type));
    }

    /**
     * One arm with no top-level union or intersection, rewritten.
     */
    private static function reindexedArm(string $arm): string
    {
        if (TsTypeShape::isWrapped($arm, '(', ')')) {
            return '('.self::reindexedType(substr($arm, 1, -1)).')';
        }

        if ($arm !== '[]' && str_ends_with($arm, '[]')) {
            return self::reindexedArm(trim(substr($arm, 0, -2))).'[]';
        }

        $values = self::numericKeyedValues([$arm]);

        return $values === null ? self::reindexedMembers($arm) : TsTypeShape::listType($values);
    }

    /**
     * A part whose own keys stay: an object literal's or a `Record`'s values rewritten, any other type as it is.
     */
    private static function reindexedMembers(string $part): string
    {
        $members = TsTypeShape::members($part);

        if ($members !== null) {
            return self::reindexedObject($part, $members);
        }

        foreach ([self::STRING_RECORD, self::NUMBER_RECORD] as $prefix) {
            if (str_starts_with($part, $prefix) && str_ends_with($part, '>')) {
                $value = substr($part, strlen($prefix), -1);
                $rewritten = self::reindexedType($value);

                return $rewritten === $value ? $part : $prefix.$rewritten.'>';
            }
        }

        return $part;
    }

    /**
     * An object literal with a named key, its member values rewritten.
     *
     * @param  list<array{string, string}>  $members
     */
    private static function reindexedObject(string $object, array $members): string
    {
        $changed = false;
        $printed = [];

        foreach ($members as [$key, $value]) {
            $rewritten = self::reindexedType($value);
            $changed = $changed || $rewritten !== $value;
            $printed[] = $key.': '.$rewritten;
        }

        return $changed ? '{ '.implode('; ', $printed).' }' : $object;
    }

    /**
     * The member values of intersection parts that each key only numbers, rewritten in order; null when a part is
     * anything else.
     *
     * @param  list<string>  $parts
     * @return list<string>|null
     */
    private static function numericKeyedValues(array $parts): ?array
    {
        $values = [];

        foreach ($parts as $part) {
            if (str_starts_with($part, self::NUMBER_RECORD) && str_ends_with($part, '>')) {
                $values[] = self::reindexedType(substr($part, strlen(self::NUMBER_RECORD), -1));

                continue;
            }

            $members = TsTypeShape::members($part) ?? [];

            if ($members === [] || ! array_all(array_column($members, 0), TsTypeShape::isNumericKey(...))) {
                return null;
            }

            foreach ($members as [, $value]) {
                $values[] = self::reindexedType($value);
            }
        }

        return $values;
    }
}
