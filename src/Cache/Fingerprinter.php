<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Cache;

use Closure;

class Fingerprinter
{
    /**
     * Compute an order-independent fingerprint over a file set, plus an optional non-file input.
     *
     * Missing files contribute a 'missing' marker so their later appearance or removal still moves the hash.
     *
     * @param  list<string>  $paths
     * @param  (Closure(string): string)|null  $hash  Reads one file's hash; hashFile() when null.
     */
    public static function fromPaths(array $paths, string $extra = '', ?Closure $hash = null): string
    {
        $hash ??= self::hashFile(...);

        $paths = array_values(array_unique($paths));
        sort($paths);

        $parts = [];

        foreach ($paths as $path) {
            $parts[] = $path.'@'.$hash($path);
        }

        if ($extra !== '') {
            $parts[] = '::extra::'.$extra;
        }

        return hash('xxh128', implode("\n", $parts));
    }

    /**
     * Hash one file's content, or 'missing' when it does not exist.
     */
    public static function hashFile(string $path): string
    {
        return is_file($path) ? (string) hash_file('xxh128', $path) : 'missing';
    }
}
