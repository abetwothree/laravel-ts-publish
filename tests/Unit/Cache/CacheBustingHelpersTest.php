<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Support\ConfigFingerprint;
use AbeTwoThree\LaravelTsPublish\Support\PackageVersion;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

/**
 * Create a directory and register it ahead of the package's own views, as a published copy of them would be.
 */
function publishedViewsDirectory(string $directory): string
{
    mkdir($directory.'/partials', recursive: true);

    View::prependNamespace('laravel-ts-publish', $directory);
    View::getFinder()->flush();

    return $directory;
}

beforeEach(function () {
    $this->views = sys_get_temp_dir().'/ts-publish-views-'.uniqid();
});

afterEach(function () {
    File::deleteDirectory($this->views);
});

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

it('changes the config fingerprint when a cached feature\'s template is edited, but not when it is only published', function () {
    $before = ConfigFingerprint::compute();

    $directory = publishedViewsDirectory($this->views);
    copy(__DIR__.'/../../../resources/views/enum.blade.php', $directory.'/enum.blade.php');

    $published = ConfigFingerprint::compute();

    file_put_contents($directory.'/enum.blade.php', "// edited\n", FILE_APPEND);

    expect($published)->toBe($before)
        ->and(ConfigFingerprint::compute())->not->toBe($published);
});

it('changes the config fingerprint when a published copy of a cached feature\'s template is deleted', function () {
    $directory = publishedViewsDirectory($this->views);
    file_put_contents($directory.'/resource.blade.php', "// customized\n");

    $customized = ConfigFingerprint::compute();

    unlink($directory.'/resource.blade.php');
    View::getFinder()->flush();

    expect(ConfigFingerprint::compute())->not->toBe($customized);
});

it('changes the config fingerprint when a view a cached template includes by name is edited', function () {
    $directory = publishedViewsDirectory($this->views);
    file_put_contents($directory.'/enum.blade.php', "@include('laravel-ts-publish::partials.banner')\n");
    file_put_contents($directory.'/partials/banner.blade.php', "// v1\n");

    $before = ConfigFingerprint::compute();

    file_put_contents($directory.'/partials/banner.blade.php', "// v2\n");

    expect(ConfigFingerprint::compute())->not->toBe($before);
});

it('keeps the config fingerprint stable when a template that renders on every run is edited', function () {
    $before = ConfigFingerprint::compute();

    $directory = publishedViewsDirectory($this->views);
    file_put_contents($directory.'/globals.blade.php', "// customized\n");

    expect(ConfigFingerprint::compute())->toBe($before);
});

it('fingerprints a cached template that cannot be resolved without throwing', function () {
    Config::set('ts-publish.enums.template', 'laravel-ts-publish::missing');

    $first = ConfigFingerprint::compute();

    expect($first)->not->toStartWith('unfingerprintable-')
        ->and(ConfigFingerprint::compute())->toBe($first);
});

it('fingerprints a template that includes itself without looping', function () {
    $directory = publishedViewsDirectory($this->views);
    file_put_contents($directory.'/enum.blade.php', "@include('laravel-ts-publish::enum')\n");

    $first = ConfigFingerprint::compute();

    expect($first)->not->toStartWith('unfingerprintable-')
        ->and(ConfigFingerprint::compute())->toBe($first);
});
