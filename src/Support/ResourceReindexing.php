<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

/**
 * Answers whether an API resource response re-indexes an array into a list.
 *
 * `ConditionallyLoadsAttributes::filter()` recurses into arrays only, never into an object, and
 * `removeMissingValues()` re-indexes an array whose keys all pass `is_numeric()`.
 *
 * @internal
 */
final class ResourceReindexing
{
    /**
     * Whether the array, or one an array holds at any depth, has only numeric keys yet is not a list.
     *
     * @param  array<array-key, mixed>  $value
     */
    public static function reindexesAsList(array $value): bool
    {
        if (! array_is_list($value) && array_all(array_keys($value), fn (int|string $key): bool => is_numeric($key))) {
            return true;
        }

        return array_any($value, fn (mixed $item): bool => is_array($item) && self::reindexesAsList($item));
    }
}
