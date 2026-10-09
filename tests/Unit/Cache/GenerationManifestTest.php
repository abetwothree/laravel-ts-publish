<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Cache\FileCacheRepository;
use AbeTwoThree\LaravelTsPublish\Cache\Fingerprinter;
use AbeTwoThree\LaravelTsPublish\Cache\GenerationManifest;
use AbeTwoThree\LaravelTsPublish\Cache\StoreCacheRepository;
use Illuminate\Support\Facades\Cache;

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

test('entry keys are hashed so store backends never see a long class pair', function () {
    $store = Cache::store('array');
    $store->clear();
    $repository = new StoreCacheRepository($store, 'ts-publish');
    $manifest = GenerationManifest::load($repository, 'test', 'hash');
    $manifest->record(str_repeat('Very\\Long\\Namespace\\', 12).'Generator::'.str_repeat('App\\Models\\', 12).'User', 'fp', 'file', [], [], base64_encode('s'));
    $manifest->save();

    // The index holds bare logical keys. A length-capped backend (memcached: 250 bytes) receives its
    // own configured cache prefix, then this repository's, then the key — so budget against all three.
    $prefix = (string) config('cache.prefix').'ts-publish:';
    $keys = collect($store->get('ts-publish:__index__', []))->map(fn (string $key): string => $prefix.$key);

    expect($keys)->not->toBeEmpty()
        ->and($keys->every(fn (string $key): bool => strlen($key) < 250))->toBeTrue()
        ->and($keys->contains(fn (string $key): bool => preg_match('/^'.preg_quote($prefix, '/').'class:[0-9a-f]{32}$/', $key) === 1))->toBeTrue();
});

it('reads each dependency file once per run, and again after save()', function () {
    $file = $this->dir.'/dep.php';
    file_put_contents($file, 'one');

    $manifest = GenerationManifest::load($this->repo, 'v1', 'cfg1');
    $first = $manifest->fingerprint([$file], 'sig');

    file_put_contents($file, 'two');

    expect($manifest->fingerprint([$file], 'sig'))->toBe($first)
        ->and(Fingerprinter::fromPaths([$file], 'sig'))->not->toBe($first);

    $manifest->save();

    expect($manifest->fingerprint([$file], 'sig'))->toBe(Fingerprinter::fromPaths([$file], 'sig'));
});

it('keeps every entry one generator class built through the prune on save, and no other', function () {
    $manifest = GenerationManifest::load($this->repo, 'v1', 'cfg1');
    $manifest->record('App\\EnumGenerator::App\\Enums\\Status', 'fp-1', 'status', [], [], 'S1');
    $manifest->record('App\\EnumGeneratorX::App\\Enums\\Status', 'fp-2', 'status', [], [], 'S2');
    $manifest->record('App\\ModelGenerator::App\\Models\\User', 'fp-3', 'user', [], [], 'S3');
    $manifest->save();

    $next = GenerationManifest::load($this->repo, 'v1', 'cfg1');
    $next->keepEntriesOf('App\\EnumGenerator');
    $next->save();

    $reloaded = GenerationManifest::load($this->repo, 'v1', 'cfg1');

    expect($reloaded->snapshot('App\\EnumGenerator::App\\Enums\\Status'))->toBe('S1')
        ->and($reloaded->snapshot('App\\EnumGeneratorX::App\\Enums\\Status'))->toBeNull()
        ->and($reloaded->snapshot('App\\ModelGenerator::App\\Models\\User'))->toBeNull();
});

it('prunes a kept entry on the next run that neither sees nor keeps it', function () {
    $manifest = GenerationManifest::load($this->repo, 'v1', 'cfg1');
    $manifest->record('App\\EnumGenerator::App\\Enums\\Gone', 'fp-1', 'gone', [], [], 'S1');
    $manifest->save();

    $kept = GenerationManifest::load($this->repo, 'v1', 'cfg1');
    $kept->keepEntriesOf('App\\EnumGenerator');
    $kept->save();

    $afterKeep = GenerationManifest::load($this->repo, 'v1', 'cfg1');
    expect($afterKeep->snapshot('App\\EnumGenerator::App\\Enums\\Gone'))->toBe('S1');
    $afterKeep->save();

    $afterPrune = GenerationManifest::load($this->repo, 'v1', 'cfg1');
    expect($afterPrune->snapshot('App\\EnumGenerator::App\\Enums\\Gone'))->toBeNull();
});
