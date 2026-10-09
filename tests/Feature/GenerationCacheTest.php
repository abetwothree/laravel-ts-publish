<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Cache\CacheBootstrap;
use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelMetadataGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Runners\Runner;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ArchiveSpreadingResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CountingModelGenerator;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\RecordingModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ThrowingResourcesCollector;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Workbench\App\Enums\Priority;
use Workbench\App\Http\Controllers\CacheBustController;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\User;

/**
 * The cache keys the array store holds for this run's repository.
 *
 * @return list<string>
 */
function storedCacheEntries(): array
{
    /** @var list<string> */
    return Cache::store('array')->get('ts-publish:__index__', []);
}

/**
 * The store key of one generator's entry for one class.
 */
function cacheEntryKey(string $generatorClass, string $fqcn): string
{
    return 'class:'.hash('xxh128', $generatorClass.'::'.$fqcn);
}

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

test('a model that becomes publishable between runs busts the cached files that may name it', function () {
    $trailFile = $this->out.'/workbench/app/packages/audit/models/audit-trail.ts';
    $archiveFile = $this->out.'/workbench/app/packages/audit/models/audit-archive.ts';

    expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0)
        ->and(file_get_contents($trailFile))->not->toContain('archive')
        ->and(file_exists($archiveFile))->toBeFalse();

    // AuditArchive had no table, so it was not published on demand. No class file and no config changes here.
    Schema::create('audit_archives', function (Blueprint $table) {
        $table->id();
        $table->foreignId('audit_trail_id');
    });

    expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0)
        ->and(file_get_contents($trailFile))->toContain('archive: AuditArchive')
        ->and(file_exists($archiveFile))->toBeTrue();
});

test('a model that becomes publishable between runs is named by a resource that read it as unknown before', function () {
    Config::set('ts-publish.resources.additional_directories', [
        ...Config::array('ts-publish.resources.additional_directories'),
        ArchiveSpreadingResource::class,
    ]);
    Config::set('ts-publish.resources.included', [ArchiveSpreadingResource::class]);

    $published = function (): string {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->out, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getFilename() === 'archive-spreading-resource.ts') {
                return (string) file_get_contents($file->getPathname());
            }
        }

        return '';
    };

    expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0)
        ->and($published())->toContain('archive: unknown;');

    // One process serves both runs, so an analysis worked out under the first run's set must not outlive it.
    Schema::create('audit_archives', function (Blueprint $table) {
        $table->id();
        $table->foreignId('audit_trail_id');
    });

    expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0)
        ->and($published())->toContain('archive: AuditArchive');
});

test('editing a class a resource reaches only by reflection rebuilds the resource', function () {
    // PHP cannot reload a class, so the scorer lives in a temp copy the test can edit; the fingerprint hashes content.
    $suffix = bin2hex(random_bytes(4));
    $sources = "$this->cacheDir-src";
    mkdir($sources);

    foreach (['Scorer', 'ScoreResource'] as $name) {
        $stub = (string) file_get_contents(__DIR__.'/../Fixtures/ReflectedReceiver/'.$name.'.php.stub');
        file_put_contents($sources.'/'.$name.'.php', str_replace('__SUFFIX__', $suffix, $stub));
        require_once $sources.'/'.$name.'.php';
    }

    $namespace = 'AbeTwoThree\\LaravelTsPublish\\Tests\\Fixtures\\ReflectedReceiver\\';
    $resource = $namespace.'ScoreResource'.$suffix;
    $scorerFile = (string) new ReflectionClass($namespace.'Scorer'.$suffix)->getFileName();
    $published = $this->out.'/abe-two-three/laravel-ts-publish/tests/fixtures/reflected-receiver/score-resource'.$suffix.'.ts';
    Config::set('ts-publish.resources.additional_directories', [$resource]);
    Config::set('ts-publish.resources.included', [$resource]);

    // A cache hit rehydrates without the container, so each resolution here is a rebuild.
    $builds = 0;
    $this->app->resolving(ResourceGenerator::class, function () use (&$builds): void {
        $builds++;
    });

    try {
        expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0)
            ->and(file_get_contents($published))->toContain('score: number;')
            ->and(CacheBootstrap::manifest(CacheBootstrap::repository())->deps(ResourceGenerator::class.'::'.$resource))
            ->toContain($scorerFile)
            ->and($builds)->toBe(1);

        expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0)
            ->and($builds)->toBe(1);

        file_put_contents($scorerFile, "\n// edited\n", FILE_APPEND);

        expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0)
            ->and($builds)->toBe(2);
    } finally {
        array_map(unlink(...), glob($sources.'/*.php') ?: []);
        @rmdir($sources);
    }
});

test('a partial run keeps the cache entries of the features it skipped', function () {
    Config::set('ts-publish.cache.store', 'array');
    Config::set('ts-publish.models.generator_class', CountingModelGenerator::class);
    Cache::store('array')->clear();
    CountingModelGenerator::$built = 0;

    expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0);
    $models = CountingModelGenerator::$built;
    expect($models)->toBeGreaterThan(0);

    expect(Artisan::call('ts:publish', ['--only-enums' => true, '--quiet' => true]))->toBe(0)
        ->and(storedCacheEntries())
        ->toContain(cacheEntryKey(EnumGenerator::class, Priority::class))
        ->toContain(cacheEntryKey(CountingModelGenerator::class, User::class))
        ->toContain(cacheEntryKey(ResourceGenerator::class, UserResource::class));

    expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0)
        ->and(CountingModelGenerator::$built)->toBe($models);
});

test('the --only-functional run vite build makes keeps the model and resource cache entries', function () {
    Config::set('ts-publish.cache.store', 'array');
    Cache::store('array')->clear();

    expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0);
    expect(Artisan::call('ts:publish', ['--only-functional' => true, '--quiet' => true]))->toBe(0)
        ->and(storedCacheEntries())
        ->toContain(cacheEntryKey(ModelGenerator::class, User::class))
        ->toContain(cacheEntryKey(ResourceGenerator::class, UserResource::class))
        ->toContain(cacheEntryKey(EnumGenerator::class, Priority::class));
});

test('a feature published by an interactive override is pruned by the next run, which its config skips', function () {
    Config::set('ts-publish.cache.store', 'array');
    Config::set('ts-publish.models.enabled', false);
    Cache::store('array')->clear();

    expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0);

    $this->artisan('ts:publish', ['--only-models' => true])
        ->expectsConfirmation('Config has models publishing disabled. Override and publish models anyway?', 'yes')
        ->assertSuccessful();

    expect(storedCacheEntries())
        ->toContain(cacheEntryKey(ModelGenerator::class, User::class))
        ->toContain(cacheEntryKey(ResourceGenerator::class, UserResource::class));

    expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0)
        ->and(storedCacheEntries())
        ->not->toContain(cacheEntryKey(ModelGenerator::class, User::class))
        ->toContain(cacheEntryKey(ResourceGenerator::class, UserResource::class));
});

test('a model metadata run keeps the model entries, and a model run keeps the metadata entries', function () {
    Config::set('ts-publish.cache.store', 'array');
    Config::set('ts-publish.models.included', [User::class]);
    Cache::store('array')->clear();

    expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0);
    expect(Artisan::call('ts:publish', ['--only-model-metadata' => true, '--quiet' => true]))->toBe(0)
        ->and(storedCacheEntries())
        ->toContain(cacheEntryKey(ModelGenerator::class, User::class))
        ->toContain(cacheEntryKey(ModelMetadataGenerator::class, User::class));
    expect(Artisan::call('ts:publish', ['--only-models' => true, '--quiet' => true]))->toBe(0)
        ->and(storedCacheEntries())
        ->toContain(cacheEntryKey(ModelGenerator::class, User::class))
        ->toContain(cacheEntryKey(ModelMetadataGenerator::class, User::class));
});

test('a --source run and a preview leave the cache as they found it', function () {
    Config::set('ts-publish.cache.store', 'array');
    Cache::store('array')->clear();

    expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0);
    $index = storedCacheEntries();
    $meta = Cache::store('array')->get('ts-publish:__meta__');

    expect(Artisan::call('ts:publish', ['--source' => User::class, '--quiet' => true]))->toBe(0)
        ->and(Artisan::call('ts:publish', ['--preview' => 'true', '--only-enums' => true, '--quiet' => true]))->toBe(0)
        ->and(storedCacheEntries())->toBe($index)
        ->and(Cache::store('array')->get('ts-publish:__meta__'))->toBe($meta);
});

test('a partial run lists the features it skipped in the globals and JSON files as the last full run did', function (string $flag) {
    Config::set('ts-publish.globals.enabled', true);
    Config::set('ts-publish.json.enabled', true);
    $globals = $this->out.'/'.Config::string('ts-publish.globals.filename');
    $json = $this->out.'/'.Config::string('ts-publish.json.filename');

    expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0);
    $fullGlobals = file_get_contents($globals);
    $fullJson = file_get_contents($json);

    expect(Artisan::call('ts:publish', [$flag => true, '--quiet' => true]))->toBe(0)
        ->and(file_get_contents($globals))->toBe($fullGlobals)
        ->and(file_get_contents($json))->toBe($fullJson);
})->with(['--only-functional', '--only-enums', '--only-models']);

test('with the cache off a partial run still writes the globals without the skipped features', function () {
    Config::set('ts-publish.cache.enabled', false);
    Config::set('ts-publish.globals.enabled', true);
    $globals = $this->out.'/'.Config::string('ts-publish.globals.filename');

    expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0)
        ->and(file_get_contents($globals))->toContain('export interface User');

    expect(Artisan::call('ts:publish', ['--only-enums' => true, '--quiet' => true]))->toBe(0)
        ->and(file_get_contents($globals))
        ->toContain('export namespace workbench.app.enums')
        ->not->toContain('export interface User');
});

test('a kept entry whose snapshot cannot be rehydrated drops only that class from the globals', function () {
    Config::set('ts-publish.cache.store', 'array');
    Config::set('ts-publish.globals.enabled', true);
    Cache::store('array')->clear();
    $globals = $this->out.'/'.Config::string('ts-publish.globals.filename');

    expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0);
    $fullGlobals = (string) file_get_contents($globals);

    // Written through the repository so the entry stays validly signed and loads; only its snapshot is unreadable.
    $repository = CacheBootstrap::repository();
    $key = cacheEntryKey(ResourceGenerator::class, UserResource::class);
    $entry = $repository->get($key);
    expect($entry)->toBeArray()->toHaveKey('snapshot');
    $repository->put($key, [...(array) $entry, 'snapshot' => 'not-base64!']);

    // The Crm module publishes a UserResource of its own, which must stay.
    $userResources = fn (string $content): int => substr_count($content, 'export interface UserResource {');

    expect(Artisan::call('ts:publish', ['--only-enums' => true, '--quiet' => true]))->toBe(0)
        ->and(file_get_contents($globals))->toContain('export interface PostResource {')
        ->and($userResources((string) file_get_contents($globals)))->toBe($userResources($fullGlobals) - 1);
});

test('the generators a partial run retains stay apart from the ones it published', function () {
    expect(new Runner)
        ->retainedModelGenerators->toBeEmpty()
        ->retainedResourceGenerators->toBeEmpty();

    $full = new Runner;
    $full->useCache(CacheBootstrap::manifest());
    $full->run();

    $partial = new Runner;
    $partial->shouldPublishModels = false;
    $partial->shouldPublishResources = false;
    $partial->useCache(CacheBootstrap::manifest());
    $partial->run();

    // The console summary counts the published collections, so a retained class must not be counted as published.
    expect($full->modelGenerators)->not->toBeEmpty()
        ->and($full->resourceGenerators)->not->toBeEmpty()
        ->and($partial->modelGenerators)->toBeEmpty()
        ->and($partial->resourceGenerators)->toBeEmpty()
        ->and($partial->retainedModelGenerators)->toHaveCount($full->modelGenerators->count())
        ->and($partial->retainedResourceGenerators)->toHaveCount($full->resourceGenerators->count())
        ->and($partial->enumGenerators)->toHaveCount($full->enumGenerators->count())
        ->and($partial->retainedEnumGenerators)->toBeEmpty();
});

test('a partial run that retains the models builds the morph map once', function (bool $laterPhases) {
    Config::set('ts-publish.inertia.enabled', $laterPhases);
    expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0);

    $resolver = new RecordingModelAttributeResolver;
    app()->instance(ModelAttributeResolver::class, $resolver);

    $runner = new Runner;
    $runner->shouldPublishModels = false;

    if (! $laterPhases) {
        $runner->shouldPublishModelMetadata = false;
        $runner->shouldPublishResources = false;
        $runner->shouldPublishRoutes = false;
        $runner->shouldPublishFormRequests = false;
        $runner->shouldPublishBroadcastEvents = false;
    }

    $runner->useCache(CacheBootstrap::manifest());
    $runner->run();

    expect($resolver->morphTargetMapBuilds)->toHaveCount(1)
        ->and($runner->retainedModelGenerators)->not->toBeEmpty();
})->with([
    'a run whose later phases read the published set' => [true],
    'a run that builds the published set only to retain the models' => [false],
]);

test('a run that ends before it retains leaves no retained generators from the run before it on the same runner', function () {
    $full = new Runner;
    $full->useCache(CacheBootstrap::manifest());
    $full->run();

    Config::set('ts-publish.globals.enabled', true);
    $runner = new Runner;
    $runner->shouldPublishModels = false;
    $runner->shouldPublishResources = false;
    $runner->useCache(CacheBootstrap::manifest());
    $runner->run();

    expect($runner->retainedModelGenerators)->not->toBeEmpty()
        ->and($runner->retainedResourceGenerators)->not->toBeEmpty();

    // The collector throws before retainSkippedGenerators() runs, so only the run boundary can have emptied them.
    Config::set('ts-publish.resources.collector_class', ThrowingResourcesCollector::class);
    $runner->shouldPublishResources = true;
    expect(fn () => $runner->run())->toThrow(RuntimeException::class);

    expect($runner->retainedEnumGenerators)->toBeEmpty()
        ->and($runner->retainedModelGenerators)->toBeEmpty()
        ->and($runner->retainedResourceGenerators)->toBeEmpty()
        ->and($runner->retainedFormRequestGenerators)->toBeEmpty()
        ->and($runner->retainedBroadcastEventGenerators)->toBeEmpty();
});

test('a template published and edited after the first run reaches the next run without --fresh', function () {
    $enumFile = $this->out.'/workbench/app/enums/priority.ts';
    expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0)
        ->and(file_get_contents($enumFile))->not->toContain('// edited template');

    // An edited enum template ahead of the package's views, as `vendor:publish` and an edit would leave them.
    $views = $this->out.'-views';
    mkdir($views);
    file_put_contents($views.'/enum.blade.php', "// edited template\n".file_get_contents(__DIR__.'/../../resources/views/enum.blade.php'));
    View::prependNamespace('laravel-ts-publish', $views);
    View::getFinder()->flush();

    try {
        expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0)
            ->and(file_get_contents($enumFile))->toStartWith("// edited template\n");
    } finally {
        unlink($views.'/enum.blade.php');
        rmdir($views);
    }
});

test('a template edited in place between two runs in one process reaches the second', function () {
    $enumFile = $this->out.'/workbench/app/enums/priority.ts';
    $views = $this->out.'-views';
    $template = $views.'/enum.blade.php';
    $package = (string) file_get_contents(__DIR__.'/../../resources/views/enum.blade.php');
    mkdir($views);
    file_put_contents($template, "// v1\n".$package);
    View::prependNamespace('laravel-ts-publish', $views);
    View::getFinder()->flush();

    try {
        expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0)
            ->and(file_get_contents($enumFile))->toStartWith("// v1\n");

        // Blade skips the expiry check for a view this process compiled already, so only a flush makes it read v2.
        file_put_contents($template, "// v2\n".$package);
        touch($template, time() + 5);

        expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0)
            ->and(file_get_contents($enumFile))->toStartWith("// v2\n");
    } finally {
        unlink($template);
        rmdir($views);
    }
});
