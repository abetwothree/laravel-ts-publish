<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Cache;

class Fingerprinter
{
    /**
     * Compute an order-independent fingerprint from a set of files by combining
     * each file's content hash. Missing files contribute a stable 'missing'
     * marker so their later appearance/removal still changes the fingerprint.
     *
     * The optional $extra string folds a non-file input (e.g. route definitions
     * read from the router) into the hash. An empty $extra is a no-op, so the
     * result stays byte-identical to a pure file-set fingerprint.
     *
     * @param  list<string>  $paths
     */
    public static function fromPaths(array $paths, string $extra = ''): string
    {
        $paths = array_values(array_unique($paths));
        sort($paths);

        $parts = [];

        foreach ($paths as $path) {
            $hash = is_file($path) ? hash_file('xxh128', $path) : 'missing';
            $parts[] = $path.'@'.$hash;
        }

        if ($extra !== '') {
            $parts[] = '::extra::'.$extra;
        }

        return hash('xxh128', implode("\n", $parts));
    }
}
