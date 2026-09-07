<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelMetadataGenerator;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Workbench\App\Http\Controllers\CacheBustController;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->out = sys_get_temp_dir().'/ts-publish-out-'.uniqid();
    $this->cacheDir = sys_get_temp_dir().'/ts-publish-cache-'.uniqid();

    Config::set('ts-publish.output_to_files', true);
    Config::set('ts-publish.output_directory', $this->out);
    Config::set('ts-publish.cache.enabled', true);
    Config::set('ts-publish.cache.store', null);
    Config::set('ts-publish.cache.directory', $this->cacheDir);
});

afterEach(function () {
    foreach ([$this->out ?? null, $this->cacheDir ?? null] as $dir) {
        if (is_string($dir) && is_dir($dir)) {
            $items = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($items as $item) {
                $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
            }
            @rmdir($dir);
        }
    }
});

test('manifest is written on first run', function () {
    $exitCode = Artisan::call('ts:publish', ['--quiet' => true]);
    expect($exitCode)->toBe(0);

    $cacheFiles = glob($this->cacheDir.'/*.cache') ?: [];
    expect($cacheFiles)->not->toBeEmpty('Expected at least one .cache file after first run');
});

test('unchanged output keeps its mtime on a second run', function () {
    $exitCode = Artisan::call('ts:publish', ['--quiet' => true]);
    expect($exitCode)->toBe(0);

    // The globals file is always written at the output_directory root.
    $globalsFile = $this->out.'/'.Config::string('ts-publish.globals.filename');
    expect(file_exists($globalsFile))->toBeTrue('Globals file must exist after first run');

    $mtime1 = filemtime($globalsFile);

    // Long enough for the filesystem to record a different mtime.
    usleep(1_100_000);

    $exitCode = Artisan::call('ts:publish', ['--quiet' => true]);
    expect($exitCode)->toBe(0);

    clearstatcache(true, $globalsFile);
    $mtime2 = filemtime($globalsFile);

    expect($mtime2)->toBe($mtime1, 'Globals file mtime changed on second run — cache did not prevent rewrite');
});

test('--fresh flag rebuilds and cache files are present after rebuild', function () {
    $exitCode = Artisan::call('ts:publish', ['--quiet' => true]);
    expect($exitCode)->toBe(0);

    $exitCode = Artisan::call('ts:publish', ['--fresh' => true, '--quiet' => true]);
    expect($exitCode)->toBe(0);

    $cacheFiles = glob($this->cacheDir.'/*.cache') ?: [];
    expect($cacheFiles)->not->toBeEmpty('Expected .cache files to be present after --fresh rebuild');
});

test('config-fingerprint change busts the cache and a full rebuild succeeds', function () {
    $exitCode = Artisan::call('ts:publish', ['--quiet' => true]);
    expect($exitCode)->toBe(0);

    // namespace_strip_prefix is part of the fingerprint hashed into the manifest header.
    Config::set('ts-publish.namespace_strip_prefix', 'Changed\\');

    $exitCode = Artisan::call('ts:publish', ['--quiet' => true]);
    expect($exitCode)->toBe(0);

    $cacheFiles = glob($this->cacheDir.'/*.cache') ?: [];
    expect($cacheFiles)->not->toBeEmpty('Expected .cache files to be present after fingerprint-busted rebuild');
});

test('output is identical whether the cache is on or off', function () {
    $exitCode = Artisan::call('ts:publish', ['--quiet' => true]);
    expect($exitCode)->toBe(0);

    $globalsFile = $this->out.'/'.Config::string('ts-publish.globals.filename');
    expect(file_exists($globalsFile))->toBeTrue();
    $cachedContent = file_get_contents($globalsFile);

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->out, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($items as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($this->out);

    Config::set('ts-publish.cache.enabled', false);
    $exitCode = Artisan::call('ts:publish', ['--quiet' => true]);
    expect($exitCode)->toBe(0);

    expect(file_exists($globalsFile))->toBeTrue();
    $uncachedContent = file_get_contents($globalsFile);

    expect($uncachedContent)->toBe($cachedContent, 'Generated globals content differs between cached and uncached runs');
});

test('deleted output files are regenerated even with a populated manifest', function () {
    $exitCode = Artisan::call('ts:publish', ['--quiet' => true]);
    expect($exitCode)->toBe(0);

    $tsFilesBefore = [];
    if (is_dir($this->out)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->out, FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.ts')) {
                $tsFilesBefore[] = $f->getPathname();
            }
        }
    }
    expect($tsFilesBefore)->not->toBeEmpty('No .ts files were written on the first run');

    // Delete the outputs but leave the cache directory intact.
    foreach ($tsFilesBefore as $tsFile) {
        @unlink($tsFile);
    }

    $tsFilesDeleted = [];
    if (is_dir($this->out)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->out, FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.ts')) {
                $tsFilesDeleted[] = $f->getPathname();
            }
        }
    }
    expect($tsFilesDeleted)->toBeEmpty('Expected all .ts files to be deleted before second run');

    // Manifest still exists, so only the missing-output check can force the rebuild.
    $exitCode = Artisan::call('ts:publish', ['--quiet' => true]);
    expect($exitCode)->toBe(0);

    $tsFilesAfter = [];
    if (is_dir($this->out)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->out, FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.ts')) {
                $tsFilesAfter[] = $f->getPathname();
            }
        }
    }
    expect($tsFilesAfter)->not->toBeEmpty('Expected .ts files to be regenerated after cache-miss due to missing outputs');
});

test('a new route on an already-cached controller busts and regenerates its output', function () {
    Config::set('ts-publish.routes.enabled', true);

    $concat = function (string $dir): string {
        if (! is_dir($dir)) {
            return '';
        }

        $all = '';

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.ts')) {
                $all .= (string) file_get_contents($f->getPathname());
            }
        }

        return $all;
    };

    // Only 'baseline' is routed here, so 'probe' cannot appear in the first run's output.
    Route::get('posts-baseline', [CacheBustController::class, 'baseline'])->name('posts.baseline');

    expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0)
        ->and($concat($this->out))->not->toContain('cache-bust-probe');

    // Only the route definition changes; CacheBustController's file — the recorded dependency —
    // does not, so the route signature is the cache's only way to notice.
    Route::post('cache-bust-probe', [CacheBustController::class, 'probe'])->name('cache.bust.probe');

    expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0)
        ->and($concat($this->out))->toContain('cache-bust-probe');
});

test('model and metadata generators for one model keep separate cache entries', function () {
    // The array backend is the only one whose stored key names are readable back out.
    Config::set('ts-publish.cache.store', 'array');
    Config::set('ts-publish.model_metadata.enabled', true);
    Config::set('ts-publish.models.included', [User::class]);
    Cache::store('array')->clear();

    expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0);

    $model = $this->out.'/workbench/app/models/user.ts';
    $metadata = $this->out.'/workbench/app/models/user_meta.ts';
    $barrel = $this->out.'/workbench/app/models/index.ts';

    // The generator FQCN is the whole discriminator: without it both phases share one entry.
    expect(Cache::store('array')->get('ts-publish:__index__', []))
        ->toContain('class:'.hash('xxh128', ModelGenerator::class.'::'.User::class))
        ->toContain('class:'.hash('xxh128', ModelMetadataGenerator::class.'::'.User::class));

    // Force the model entry down the miss path while the metadata entry hits, then check neither
    // rehydrated the other's transformer: a shared key would leave the barrel with one export.
    @unlink($model);

    expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0)
        ->and(file_get_contents($model))->toContain('export interface User')
        ->and(file_get_contents($metadata))->toContain('export const UserModelMetadata')
        ->and(file_get_contents($barrel))->toBe("export * from './user';\nexport * from './user_meta';");
});

test('a morph map registered after the first run busts only the cached metadata file', function () {
    Config::set('ts-publish.model_metadata.enabled', true);
    Config::set('ts-publish.models.included', [User::class]);

    $model = $this->out.'/workbench/app/models/user.ts';
    $metadata = $this->out.'/workbench/app/models/user_meta.ts';

    expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0)
        ->and(file_get_contents($metadata))->toContain("morphClass: 'Workbench\\\\App\\\\Models\\\\User'");

    $modelMtime = filemtime($model);

    // Long enough for the filesystem to record a different mtime.
    usleep(1_100_000);

    $previousMorphMap = Relation::morphMap();
    Relation::morphMap(['user_alias' => User::class], false);

    try {
        // Only the morph map changes; User's file — the recorded dependency — does not, so the
        // provider payload folded into the signature is the cache's only way to notice.
        expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0)
            ->and(file_get_contents($metadata))->toContain("morphClass: 'user_alias'");

        clearstatcache(true, $model);

        // The interface has no such off-file input, so its own entry must still hit.
        expect(filemtime($model))->toBe($modelMtime, 'Model interface was rewritten by a metadata-only cache bust');
    } finally {
        Relation::morphMap($previousMorphMap, false);
    }
});
