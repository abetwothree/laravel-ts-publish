<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;

/**
 * Joins import maps, module path => the names a file imports from it, into one.
 *
 * @phpstan-import-type TypesImportMap from Datable
 *
 * @internal
 */
final class ImportMaps
{
    /**
     * Join import maps, each name once per path, names and paths sorted.
     *
     * @param  TypesImportMap  ...$maps
     * @return TypesImportMap
     */
    public static function merge(array ...$maps): array
    {
        $merged = [];

        foreach ($maps as $map) {
            foreach ($map as $path => $names) {
                $merged[$path] = [...$merged[$path] ?? [], ...$names];
            }
        }

        foreach ($merged as $path => $names) {
            $names = array_values(array_unique($names));
            sort($names);
            $merged[$path] = $names;
        }

        ksort($merged);

        return $merged;
    }
}
