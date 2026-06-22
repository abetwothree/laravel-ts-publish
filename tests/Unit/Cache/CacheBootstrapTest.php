<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Cache\CacheBootstrap;
use AbeTwoThree\LaravelTsPublish\Cache\FileCacheRepository;
use AbeTwoThree\LaravelTsPublish\Cache\StoreCacheRepository;
use Illuminate\Support\Facades\Config;

it('builds a file repository when no store is configured', function () {
    Config::set('ts-publish.cache.store', null);
    Config::set('ts-publish.cache.directory', sys_get_temp_dir().'/ts-publish-bootstrap-'.uniqid());

    expect(CacheBootstrap::repository())->toBeInstanceOf(FileCacheRepository::class);
});

it('builds a store repository when a store is configured', function () {
    Config::set('ts-publish.cache.store', 'array');

    expect(CacheBootstrap::repository())->toBeInstanceOf(StoreCacheRepository::class);
});

it('loads a manifest stamped with the current version and config hash', function () {
    Config::set('ts-publish.cache.store', null);
    Config::set('ts-publish.cache.directory', sys_get_temp_dir().'/ts-publish-bootstrap-'.uniqid());

    $manifest = CacheBootstrap::manifest();

    expect($manifest->hit('Nothing', 'x'))->toBeFalse();
});
