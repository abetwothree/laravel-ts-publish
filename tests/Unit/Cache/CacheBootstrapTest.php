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

it('signs file cache payloads with the application key by default', function () {
    $dir = sys_get_temp_dir().'/ts-publish-signing-'.uniqid();
    Config::set('ts-publish.cache.store', null);
    Config::set('ts-publish.cache.key', null);
    Config::set('ts-publish.cache.directory', $dir);
    Config::set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    $repo = CacheBootstrap::repository();
    $repo->put('signed-default', ['x' => 1]);

    $file = $dir.'/'.hash('xxh128', 'signed-default').'.cache';
    [$signature] = explode(':', (string) file_get_contents($file), 2);

    // Signed payloads are "<sha256-hmac>:<serialized>" — the prefix is 64 hex
    // chars. An UNSIGNED serialized array would start with "a" (e.g. "a:1:{...}").
    expect(strlen($signature))->toBe(64)
        ->and(ctype_xdigit($signature))->toBeTrue()
        ->and($repo->get('signed-default'))->toBe(['x' => 1]);

    array_map('unlink', glob($dir.'/*') ?: []);
    @rmdir($dir);
});
