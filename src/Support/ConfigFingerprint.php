<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

use Illuminate\Support\Facades\Config;
use Throwable;

class ConfigFingerprint
{
    /**
     * Hash the output-affecting `ts-publish` config so the cache busts when
     * templates, directories, enabled flags, type maps, etc. change. The
     * `cache` sub-array is excluded: toggling the cache must not bust outputs.
     */
    public static function compute(): string
    {
        /** @var array<string, mixed> $config */
        $config = Config::array('ts-publish');

        unset($config['cache']);

        self::ksortRecursive($config);

        try {
            return hash('xxh128', serialize($config));
        } catch (Throwable) {
            // The config holds a non-serializable value (e.g. a closure). The
            // cache must never crash generation, so fall back to a per-run unique
            // token: the manifest header will never match a stored one, forcing a
            // safe full rebuild this run instead of risking stale output.
            return 'unfingerprintable-'.bin2hex(random_bytes(16));
        }
    }

    /**
     * Recursively sort an array by key so the fingerprint is independent of the
     * declaration order of config entries.
     *
     * @param  array<array-key, mixed>  $array
     */
    private static function ksortRecursive(array &$array): void
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                self::ksortRecursive($value);
            }
        }

        unset($value);

        ksort($array);
    }
}
