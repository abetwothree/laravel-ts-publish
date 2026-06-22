<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Cache;

use AbeTwoThree\LaravelTsPublish\Cache\Contracts\CacheRepository;
use AbeTwoThree\LaravelTsPublish\Support\ConfigFingerprint;
use AbeTwoThree\LaravelTsPublish\Support\PackageVersion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class CacheBootstrap
{
    /**
     * Whether the generation cache is enabled in config.
     */
    public static function enabled(): bool
    {
        return Config::boolean('ts-publish.cache.enabled', true);
    }

    /**
     * Build the configured cache backend: a Laravel store when
     * `ts-publish.cache.store` is set, otherwise the file backend.
     */
    public static function repository(): CacheRepository
    {
        $store = Config::get('ts-publish.cache.store');

        if (is_string($store) && $store !== '') {
            return new StoreCacheRepository(Cache::store($store), 'ts-publish');
        }

        $directory = Config::string('ts-publish.cache.directory', storage_path('framework/cache/ts-publish'));
        $key = Config::get('ts-publish.cache.key');

        return new FileCacheRepository($directory, is_string($key) && $key !== '' ? $key : null);
    }

    /**
     * Load a manifest stamped with the current package version and config hash,
     * busting the cache automatically when either has changed.
     */
    public static function manifest(?CacheRepository $repository = null): GenerationManifest
    {
        return GenerationManifest::load(
            $repository ?? self::repository(),
            PackageVersion::current(),
            ConfigFingerprint::compute(),
        );
    }
}
