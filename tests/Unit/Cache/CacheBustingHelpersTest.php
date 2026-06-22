<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Support\ConfigFingerprint;
use AbeTwoThree\LaravelTsPublish\Support\PackageVersion;
use Illuminate\Support\Facades\Config;

it('returns a non-empty package version string', function () {
    expect(PackageVersion::current())->toBeString()->not->toBe('');
});

it('changes the config fingerprint when an output-affecting key changes', function () {
    $before = ConfigFingerprint::compute();

    Config::set('ts-publish.namespace_strip_prefix', 'Modules\\');

    expect(ConfigFingerprint::compute())->not->toBe($before);
});

it('ignores the cache config slice when fingerprinting', function () {
    $before = ConfigFingerprint::compute();

    Config::set('ts-publish.cache.enabled', false);
    Config::set('ts-publish.cache.store', 'redis');

    expect(ConfigFingerprint::compute())->toBe($before);
});
