<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Cache\FileCacheRepository;
use AbeTwoThree\LaravelTsPublish\Cache\GenerationManifest;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/ts-publish-manifest-'.uniqid();
    $this->repo = new FileCacheRepository($this->dir, null);
});

afterEach(function () {
    array_map('unlink', glob($this->dir.'/*') ?: []);
    @rmdir($this->dir);
});

it('reports a miss for an unknown class', function () {
    $manifest = GenerationManifest::load($this->repo, 'v1', 'cfg1');

    expect($manifest->hit('App\\Models\\User', 'fp-1'))->toBeFalse();
});

it('records then hits a class with a matching fingerprint', function () {
    $manifest = GenerationManifest::load($this->repo, 'v1', 'cfg1');
    $manifest->record('App\\Models\\User', 'fp-1', 'user', [], [], 'SNAPSHOT');
    $manifest->save();

    $reloaded = GenerationManifest::load($this->repo, 'v1', 'cfg1');

    expect($reloaded->hit('App\\Models\\User', 'fp-1'))->toBeTrue()
        ->and($reloaded->hit('App\\Models\\User', 'fp-CHANGED'))->toBeFalse()
        ->and($reloaded->snapshot('App\\Models\\User'))->toBe('SNAPSHOT')
        ->and($reloaded->filename('App\\Models\\User'))->toBe('user');
});

it('misses when a recorded output file no longer exists on disk', function () {
    $output = $this->dir.'/user.ts';
    file_put_contents($output, 'export interface User {}');

    $manifest = GenerationManifest::load($this->repo, 'v1', 'cfg1');
    $manifest->record('App\\Models\\User', 'fp-1', 'user', [], [$output], 'SNAPSHOT');
    $manifest->save();

    $reloaded = GenerationManifest::load($this->repo, 'v1', 'cfg1');
    expect($reloaded->hit('App\\Models\\User', 'fp-1'))->toBeTrue();

    unlink($output);

    $afterDelete = GenerationManifest::load($this->repo, 'v1', 'cfg1');
    expect($afterDelete->hit('App\\Models\\User', 'fp-1'))->toBeFalse();
});

it('persists and returns recorded dependency paths', function () {
    $manifest = GenerationManifest::load($this->repo, 'v1', 'cfg1');
    $manifest->record('App\\Models\\User', 'fp-1', 'user', ['/a.php', '/b.php'], [], 'SNAPSHOT');
    $manifest->save();

    $reloaded = GenerationManifest::load($this->repo, 'v1', 'cfg1');

    expect($reloaded->deps('App\\Models\\User'))->toBe(['/a.php', '/b.php']);
});

it('busts the whole cache when the version changes', function () {
    $manifest = GenerationManifest::load($this->repo, 'v1', 'cfg1');
    $manifest->record('App\\Models\\User', 'fp-1', 'user', [], [], 'SNAPSHOT');
    $manifest->save();

    $reloaded = GenerationManifest::load($this->repo, 'v2', 'cfg1');

    expect($reloaded->hit('App\\Models\\User', 'fp-1'))->toBeFalse();
});

it('busts the whole cache when the config hash changes', function () {
    $manifest = GenerationManifest::load($this->repo, 'v1', 'cfg1');
    $manifest->record('App\\Models\\User', 'fp-1', 'user', [], [], 'SNAPSHOT');
    $manifest->save();

    $reloaded = GenerationManifest::load($this->repo, 'v1', 'cfg2');

    expect($reloaded->hit('App\\Models\\User', 'fp-1'))->toBeFalse();
});

it('prunes classes not seen during the run on save', function () {
    $manifest = GenerationManifest::load($this->repo, 'v1', 'cfg1');
    $manifest->record('App\\Models\\User', 'fp-1', 'user', [], [], 'S1');
    $manifest->record('App\\Models\\Post', 'fp-2', 'post', [], [], 'S2');
    $manifest->save();

    // New run: only User is re-recorded; Post is now gone from the source tree.
    $next = GenerationManifest::load($this->repo, 'v1', 'cfg1');
    $next->markSeen('App\\Models\\User');
    $next->save();

    $reloaded = GenerationManifest::load($this->repo, 'v1', 'cfg1');

    expect($reloaded->hit('App\\Models\\User', 'fp-1'))->toBeTrue()
        ->and($reloaded->snapshot('App\\Models\\Post'))->toBeNull();
});
