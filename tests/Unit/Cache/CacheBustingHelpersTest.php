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

// A relation aggregate publishes as its connection's driver returns it, and a model may name any configured connection.
it('changes the config fingerprint when a database driver changes', function () {
    $sqlite = ConfigFingerprint::compute();

    config()->set('database.connections.testing.driver', 'mysql');
    $mysql = ConfigFingerprint::compute();

    config()->set('database.default', 'laravel13_secondary');

    expect($mysql)->not->toBe($sqlite)
        ->and(ConfigFingerprint::compute())->not->toBe($mysql)->not->toBe($sqlite);
});

// Laravel connects with the driver a connection's url names, over its `driver` key, and getDriverName() reports it.
it('changes the config fingerprint when only a connection\'s url changes its driver', function () {
    config()->set('database.connections.reporting', ['driver' => 'mysql', 'url' => 'mysql://root@127.0.0.1/forge']);
    $mysql = ConfigFingerprint::compute();

    config()->set('database.connections.reporting.url', 'pgsql://root@127.0.0.1/forge');

    expect(ConfigFingerprint::compute())->not->toBe($mysql);
});

it('does not throw and forces a rebuild when a connection\'s url is malformed', function () {
    config()->set('database.connections.reporting', ['driver' => 'mysql', 'url' => 'mysql://root@127.0.0.1:port/forge']);

    $first = ConfigFingerprint::compute();

    expect($first)->toBeString()->not->toBe('')
        ->and(ConfigFingerprint::compute())->not->toBe($first);
});

it('ignores the cache config slice when fingerprinting', function () {
    $before = ConfigFingerprint::compute();

    Config::set('ts-publish.cache.enabled', false);
    Config::set('ts-publish.cache.store', 'redis');

    expect(ConfigFingerprint::compute())->toBe($before);
});

it('does not throw and forces a rebuild when config holds a non-serializable value', function () {
    Config::set('ts-publish.routes.transformer_factory', fn () => 'x');

    $first = ConfigFingerprint::compute();
    $second = ConfigFingerprint::compute();

    expect($first)->toBeString()->not->toBe('')
        ->and($second)->not->toBe($first); // unique per call → cache safely busts, never crashes
});
