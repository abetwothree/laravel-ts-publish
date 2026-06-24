<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Workbench\App\Http\Controllers\CacheBustController;

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
    // Run once to populate the cache
    $exitCode = Artisan::call('ts:publish', ['--quiet' => true]);
    expect($exitCode)->toBe(0);

    // The globals file is always written at the output_directory root
    $globalsFile = $this->out.'/'.Config::string('ts-publish.globals.filename');
    expect(file_exists($globalsFile))->toBeTrue('Globals file must exist after first run');

    $mtime1 = filemtime($globalsFile);

    // Sleep long enough to detect a filesystem mtime change (1.1 s)
    usleep(1_100_000);

    // Run again — cache should hit; the file must NOT be rewritten
    $exitCode = Artisan::call('ts:publish', ['--quiet' => true]);
    expect($exitCode)->toBe(0);

    clearstatcache(true, $globalsFile);
    $mtime2 = filemtime($globalsFile);

    expect($mtime2)->toBe($mtime1, 'Globals file mtime changed on second run — cache did not prevent rewrite');
});

test('--fresh flag rebuilds and cache files are present after rebuild', function () {
    // First run — warms the cache
    $exitCode = Artisan::call('ts:publish', ['--quiet' => true]);
    expect($exitCode)->toBe(0);

    // Second run with --fresh — flushes, then rebuilds
    $exitCode = Artisan::call('ts:publish', ['--fresh' => true, '--quiet' => true]);
    expect($exitCode)->toBe(0);

    // Cache directory must still contain .cache files (rebuilt, not permanently deleted)
    $cacheFiles = glob($this->cacheDir.'/*.cache') ?: [];
    expect($cacheFiles)->not->toBeEmpty('Expected .cache files to be present after --fresh rebuild');
});

test('config-fingerprint change busts the cache and a full rebuild succeeds', function () {
    // First run — warms the cache with the current fingerprint
    $exitCode = Artisan::call('ts:publish', ['--quiet' => true]);
    expect($exitCode)->toBe(0);

    // Alter a config value that is part of the fingerprint
    Config::set('ts-publish.namespace_strip_prefix', 'Changed\\');

    // Second run — the manifest header mismatch must flush + rebuild cleanly
    $exitCode = Artisan::call('ts:publish', ['--quiet' => true]);
    expect($exitCode)->toBe(0);

    // The cache must have been recreated (not empty after the bust+rebuild)
    $cacheFiles = glob($this->cacheDir.'/*.cache') ?: [];
    expect($cacheFiles)->not->toBeEmpty('Expected .cache files to be present after fingerprint-busted rebuild');
});

test('output is identical whether the cache is on or off', function () {
    // Run with cache enabled
    $exitCode = Artisan::call('ts:publish', ['--quiet' => true]);
    expect($exitCode)->toBe(0);

    $globalsFile = $this->out.'/'.Config::string('ts-publish.globals.filename');
    expect(file_exists($globalsFile))->toBeTrue();
    $cachedContent = file_get_contents($globalsFile);

    // Remove generated files and disable the cache, then re-run
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
    // First run — warms the cache and writes all .ts files
    $exitCode = Artisan::call('ts:publish', ['--quiet' => true]);
    expect($exitCode)->toBe(0);

    // Confirm .ts files were written
    $tsFilesBefore = [];
    if (is_dir($this->out)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->out, FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.ts')) {
                $tsFilesBefore[] = $f->getPathname();
            }
        }
    }
    expect($tsFilesBefore)->not->toBeEmpty('No .ts files were written on the first run');

    // Recursively delete all .ts files while keeping the cache dir intact
    foreach ($tsFilesBefore as $tsFile) {
        @unlink($tsFile);
    }

    // Verify files are gone
    $tsFilesDeleted = [];
    if (is_dir($this->out)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->out, FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.ts')) {
                $tsFilesDeleted[] = $f->getPathname();
            }
        }
    }
    expect($tsFilesDeleted)->toBeEmpty('Expected all .ts files to be deleted before second run');

    // Second run — manifest exists but outputs are missing, so hit() returns false → full rebuild
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

    // Ensure CacheBustController is published AND cached on the first run.
    // Only the 'baseline' action is routed — 'probe' is not yet wired up.
    Route::get('posts-baseline', [CacheBustController::class, 'baseline'])->name('posts.baseline');

    expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0)
        ->and($concat($this->out))->not->toContain('cache-bust-probe');

    // Add a SECOND route to the same, already-cached controller. Only the route
    // DEFINITION changes — CacheBustController's class file (a recorded dependency)
    // does not — so a correct cache must detect the change via the route
    // signature and regenerate. A buggy cache HITs and the probe URI is absent.
    Route::post('cache-bust-probe', [CacheBustController::class, 'probe'])->name('cache.bust.probe');

    expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0)
        ->and($concat($this->out))->toContain('cache-bust-probe');
});
