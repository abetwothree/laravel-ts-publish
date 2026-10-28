<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use Illuminate\Support\Str;

/**
 * Joins import maps, module path => the names a file imports from it, into one, and reads the names they bind.
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

    /**
     * The name one imported name binds in the file: `X as Y` binds `Y`.
     */
    public static function localName(string $name): string
    {
        return Str::afterLast($name, ' as ');
    }

    /**
     * The names an import map binds in the file, in its order, each as localName() reads it.
     *
     * @param  TypesImportMap  $imports
     * @return list<string>
     */
    public static function localNames(array $imports): array
    {
        return array_map(self::localName(...), array_merge(...array_values($imports)));
    }
}
