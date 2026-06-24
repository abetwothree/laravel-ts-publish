<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Cache\Contracts;

interface ProvidesCacheSignature
{
    /**
     * Return a deterministic signature folded into a class's cache fingerprint,
     * capturing inputs that are NOT files (e.g. route definitions read from the
     * router). Changing the returned value forces a cache miss on the next run.
     */
    public static function cacheSignature(string $fqcn): string;
}
