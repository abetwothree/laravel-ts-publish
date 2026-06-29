<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Runners;

use AbeTwoThree\LaravelTsPublish\Cache\Contracts\ProvidesCacheSignature;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Cache\Fingerprinter;
use AbeTwoThree\LaravelTsPublish\Cache\GenerationManifest;
use AbeTwoThree\LaravelTsPublish\Cache\OutputRecorder;
use AbeTwoThree\LaravelTsPublish\Collectors\ModelsCollector;
use AbeTwoThree\LaravelTsPublish\Generators\BroadcastEventGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\CoreGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\FormRequestGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\RouteGenerator;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Transformers\CoreTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\BarrelWriter;
use AbeTwoThree\LaravelTsPublish\Writers\GlobalsWriter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Laravel\Prompts\Support\Logger;
use Throwable;

abstract class BaseRunner
{
    /** Services */
    protected BarrelWriter $barrelWriter;

    protected GlobalsWriter $globalsWriter;

    protected ?GenerationManifest $manifest = null;

    /** Control flags (set by TsPublishCommand before run()) */
    public bool $shouldPublishEnums = true;

    public bool $shouldPublishModels = true;

    public bool $shouldPublishResources = true;

    public bool $shouldPublishRoutes = true;

    public bool $shouldPublishFormRequests = true;

    public bool $shouldPublishBroadcastChannels = true;

    public bool $shouldPublishBroadcastEvents = true;

    /** Enums */

    /** @var Collection<int, EnumGenerator> */
    public protected(set) Collection $enumGenerators;

    /** @var array<string, string> Barrel contents keyed by namespace path */
    public protected(set) array $enumModularBarrels = [];

    /** Models */

    /** @var Collection<int, ModelGenerator> */
    public protected(set) Collection $modelGenerators;

    /** @var array<string, string> Barrel contents keyed by namespace path */
    public protected(set) array $modelModularBarrels = [];

    /** Resources */

    /** @var Collection<int, ResourceGenerator> */
    public protected(set) Collection $resourceGenerators;

    /** @var array<string, string> Barrel contents keyed by namespace path */
    public protected(set) array $resourceModularBarrels = [];

    /** Routes */

    /** @var Collection<int, RouteGenerator> */
    public protected(set) Collection $routeGenerators;

    /** @var array<string, string> Barrel contents keyed by namespace path */
    public protected(set) array $routeModularBarrels = [];

    /** Form Requests */

    /** @var Collection<int, FormRequestGenerator> */
    public protected(set) Collection $formRequestGenerators;

    /** @var array<string, string> Barrel contents keyed by namespace path */
    public protected(set) array $formRequestModularBarrels = [];

    /** Broadcast Channels */
    public protected(set) string $broadcastChannelsContent = '';

    /** Broadcast Events */

    /** @var Collection<int, BroadcastEventGenerator> */
    public protected(set) Collection $broadcastEventGenerators;

    /** @var array<string, string> Barrel contents keyed by namespace path */
    public protected(set) array $broadcastEventModularBarrels = [];

    public protected(set) string $broadcastEventsIndexContent = '';

    public protected(set) string $broadcastEventsEchoContent = '';

    /** Cross-cutting outputs */
    public protected(set) string $globalsContent = '';

    public protected(set) string $jsonContent = '';

    public protected(set) string $watcherJsonContent = '';

    public protected(set) string $viteEnvContent = '';

    public protected(set) string $inertiaConfigContent = '';

    /**
     * Live task logger for per-phase status output during a run.
     */
    public protected(set) ?Logger $logger = null;

    abstract public function run(): void;

    /**
     * Attach a Prompts task logger so each generation phase can report progress.
     */
    public function setLogger(?Logger $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Attach a generation manifest so per-class builds can be cached.
     */
    public function useCache(GenerationManifest $manifest): void
    {
        $this->manifest = $manifest;
    }

    /**
     * The attached generation manifest, or null when caching is bypassed.
     */
    public function manifest(): ?GenerationManifest
    {
        return $this->manifest;
    }

    /**
     * Build a generator for $fqcn, reusing the cached snapshot when its stored
     * dependencies are unchanged and its outputs still exist. On a miss, build
     * normally (transform + write) and record a fresh snapshot.
     *
     * The HIT fingerprint is recomputed over the SAME dependency paths recorded
     * on the last build, so editing any dependency (the class's own file, its
     * reflection ancestry, a related model, or a nested resource) changes a
     * hash_file result, flips the fingerprint, and forces a rebuild.
     *
     * @template T of CoreGenerator
     *
     * @param  class-string<T>  $generatorClass
     * @return T
     */
    protected function cachedGenerate(string $fqcn, string $generatorClass): CoreGenerator
    {
        // No cache (disabled or --source), or a custom generator that cannot be
        // rehydrated from a snapshot: build normally, no recording. Guarding on
        // fromCache() keeps a custom `*.generator_class` that does not use the
        // RehydratesFromCache trait from fatally calling an undefined
        // ::fromCache() on a later cache hit.
        if ($this->manifest === null || ! method_exists($generatorClass, 'fromCache')) {
            /** @var T $generator */
            $generator = resolve($generatorClass, ['findable' => $fqcn]);

            return $generator;
        }

        $this->manifest->markSeen($fqcn);

        // Fold a non-file signature (e.g. route definitions read from the router)
        // into the fingerprint so inputs that live outside any class file still
        // bust the cache. Empty for generators that do not provide one.
        $signature = is_subclass_of($generatorClass, ProvidesCacheSignature::class, true)
            ? $generatorClass::cacheSignature($fqcn)
            : '';

        // HIT: recompute the fingerprint over the previously recorded deps + signature.
        $storedDeps = $this->manifest->deps($fqcn);

        if ($storedDeps !== [] && $this->manifest->hit($fqcn, Fingerprinter::fromPaths($storedDeps, $signature))) {
            $snapshot = $this->manifest->snapshot($fqcn);
            $filename = $this->manifest->filename($fqcn);

            if ($snapshot !== null && $filename !== null) {
                $decoded = base64_decode($snapshot, true);

                if ($decoded !== false) {
                    try {
                        $transformer = unserialize($decoded);
                    } catch (Throwable) {
                        $transformer = null;
                    }

                    if ($transformer instanceof CoreTransformer) {
                        /** @var T $generator */
                        $generator = $generatorClass::fromCache($fqcn, $transformer, $filename);

                        return $generator;
                    }
                }
            }
        }

        // MISS: build normally while recording dependencies + outputs.
        DependencyRecorder::start();
        DependencyRecorder::recordClass($fqcn);
        OutputRecorder::start();

        /** @var T $generator */
        $generator = resolve($generatorClass, ['findable' => $fqcn]);

        $deps = DependencyRecorder::paths();
        $outputs = OutputRecorder::paths();
        DependencyRecorder::stop();
        OutputRecorder::stop();

        if (! isset($generator->transformer) || ! $generator->transformer instanceof CoreTransformer) {
            return $generator;
        }

        /** @var CoreTransformer<mixed> $transformer */
        $transformer = $generator->transformer;
        try {
            $snapshot = base64_encode(serialize($transformer));
        } catch (Throwable) {
            // A transformer holding a non-serializable value cannot be cached;
            // skip recording it (this class simply rebuilds next run) rather than
            // crashing the publish. Caching is best-effort and must never break
            // generation.
            return $generator;
        }

        $this->manifest->record(
            $fqcn,
            Fingerprinter::fromPaths($deps, $signature),
            $generator->filename(),
            $deps,
            $outputs,
            $snapshot,
        );

        return $generator;
    }

    /**
     * Builds the morph target map for all models, allowing MorphTo relations to be resolved to precise union types.
     *
     * @return list<class-string>
     */
    protected function buildModelMorphTargetMap(): array
    {
        /** @var ModelsCollector $collector */
        $collector = resolve(Config::string('ts-publish.models.collector_class', ModelsCollector::class));

        /** @var list<class-string> $modelClasses */
        $modelClasses = $collector->collect()->all();

        // Pre-scan all models to build the morph target map so that MorphTo
        // relations can be resolved to precise union types.
        resolve(ModelAttributeResolver::class)->buildMorphTargetMap($modelClasses);

        return $modelClasses;
    }
}
