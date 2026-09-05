<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Cache\CacheBootstrap;
use AbeTwoThree\LaravelTsPublish\Cache\Contracts\ProvidesCacheSignature;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Cache\OutputRecorder;
use AbeTwoThree\LaravelTsPublish\Generators\CoreGenerator;
use AbeTwoThree\LaravelTsPublish\Runners\BaseRunner;
use AbeTwoThree\LaravelTsPublish\Transformers\CoreTransformer;
use Illuminate\Support\Facades\Config;
use Workbench\App\Models\User;

/**
 * A custom generator that does NOT use RehydratesFromCache (no ::fromCache()).
 *
 * @extends CoreGenerator<object>
 */
class GuardFixtureGenerator extends CoreGenerator
{
    public object $transformer;

    public function generate(): string
    {
        $this->transformer = (object) ['ok' => true];

        return $this->content = 'fixture-output';
    }

    public function filename(): string
    {
        return 'fixture';
    }
}

/** @extends CoreGenerator<object> */
class ThrowingCachedFixtureGenerator extends CoreGenerator implements ProvidesCacheSignature
{
    /**
     * Return a stable cache signature.
     */
    public static function cacheSignature(string $fqcn): string
    {
        return 'stable';
    }

    /**
     * Rehydrate a cached fixture generator.
     */
    public static function fromCache(string $findable, CoreTransformer $transformer, string $filename): static
    {
        throw new RuntimeException('Unexpected cache hit.');
    }

    /**
     * Fail while generating the fixture.
     */
    public function generate(): string
    {
        throw new RuntimeException('Generation failed.');
    }

    /**
     * Return the fixture filename.
     */
    public function filename(): string
    {
        return 'throwing-fixture';
    }
}

/** @extends CoreGenerator<object> */
class ThrowingSignatureFixtureGenerator extends ThrowingCachedFixtureGenerator
{
    /**
     * Fail before the cache entry is marked as seen.
     */
    public static function cacheSignature(string $fqcn): string
    {
        throw new RuntimeException('Signature failed.');
    }
}

beforeEach(function () {
    Config::set('ts-publish.cache.store', null);
    Config::set('ts-publish.cache.directory', sys_get_temp_dir().'/ts-publish-guard-'.uniqid());

    $this->runner = new class extends BaseRunner
    {
        public function run(): void {}

        public function build(string $fqcn, string $generatorClass): CoreGenerator
        {
            return $this->cachedGenerate($fqcn, $generatorClass);
        }
    };
});

it('does not crash and does not cache a generator without fromCache()', function () {
    $manifest = CacheBootstrap::manifest();
    $this->runner->useCache($manifest);

    // Two builds: with a missing fromCache(), the second must NOT take a hit path.
    $first = $this->runner->build(User::class, GuardFixtureGenerator::class);
    $second = $this->runner->build(User::class, GuardFixtureGenerator::class);

    expect($first)->toBeInstanceOf(GuardFixtureGenerator::class)
        ->and($second)->toBeInstanceOf(GuardFixtureGenerator::class)
        ->and($manifest->snapshot(User::class))->toBeNull();
});

it('stops dependency and output recording when generation throws', function () {
    $this->runner->useCache(CacheBootstrap::manifest());

    expect(fn () => $this->runner->build(User::class, ThrowingCachedFixtureGenerator::class))
        ->toThrow(RuntimeException::class, 'Generation failed.');

    DependencyRecorder::record('/tmp/leaked-dependency.php');
    OutputRecorder::record('/tmp/leaked-output.ts');

    expect(DependencyRecorder::paths())->not->toContain('/tmp/leaked-dependency.php')
        ->and(OutputRecorder::paths())->not->toContain('/tmp/leaked-output.ts');
});

it('does not mark a cache entry seen before its signature succeeds', function () {
    $manifest = CacheBootstrap::manifest();
    $cacheKey = ThrowingSignatureFixtureGenerator::class.'::'.User::class;
    $manifest->record($cacheKey, 'fingerprint', 'fixture', [], [], base64_encode('snapshot'));
    $manifest->save();

    $manifest = CacheBootstrap::manifest();
    $this->runner->useCache($manifest);

    expect(fn () => $this->runner->build(User::class, ThrowingSignatureFixtureGenerator::class))
        ->toThrow(RuntimeException::class, 'Signature failed.');

    $manifest->save();

    expect($manifest->snapshot($cacheKey))->toBeNull();
});
