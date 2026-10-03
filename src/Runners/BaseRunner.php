<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Runners;

use AbeTwoThree\LaravelTsPublish\Cache\Contracts\ProvidesCacheSignature;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Cache\Fingerprinter;
use AbeTwoThree\LaravelTsPublish\Cache\GenerationManifest;
use AbeTwoThree\LaravelTsPublish\Cache\OutputRecorder;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedModelRegistry;
use AbeTwoThree\LaravelTsPublish\Collectors\CoreCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\ModelsCollector;
use AbeTwoThree\LaravelTsPublish\Generators\BroadcastEventGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\CoreGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\FormRequestGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelMetadataGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\RouteGenerator;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Transformers\CoreTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\BarrelWriter;
use AbeTwoThree\LaravelTsPublish\Writers\GlobalsWriter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Laravel\Prompts\Support\Logger;
use Throwable;

/**
 * @phpstan-type ModelMetadataFailure = array{subject: string, message: string}
 */
abstract class BaseRunner
{
    protected BarrelWriter $barrelWriter;

    protected GlobalsWriter $globalsWriter;

    protected ?GenerationManifest $manifest = null;

    /** Control flags (set by TsPublishCommand before run()) */
    public bool $shouldPublishEnums = true;

    public bool $shouldPublishModels = true;

    public bool $shouldPublishModelMetadata = true;

    public bool $shouldPublishResources = true;

    public bool $shouldPublishRoutes = true;

    public bool $shouldPublishFormRequests = true;

    public bool $shouldPublishBroadcastChannels = true;

    public bool $shouldPublishBroadcastEvents = true;

    /** @var Collection<int, EnumGenerator> */
    public protected(set) Collection $enumGenerators;

    /** @var array<string, string> Barrel contents keyed by namespace path */
    public protected(set) array $enumModularBarrels = [];

    /** @var Collection<int, ModelGenerator> */
    public protected(set) Collection $modelGenerators;

    /** @var Collection<int, ModelMetadataGenerator> */
    public protected(set) Collection $modelMetadataGenerators;

    /** @var array<string, string> Barrel contents keyed by namespace path */
    public protected(set) array $modelModularBarrels = [];

    /** @var list<ModelMetadataFailure> Models whose metadata threw; the command exits non-zero after publishing. */
    public protected(set) array $modelMetadataFailures = [];

    /** @var Collection<int, ResourceGenerator> */
    public protected(set) Collection $resourceGenerators;

    /** @var array<string, string> Barrel contents keyed by namespace path */
    public protected(set) array $resourceModularBarrels = [];

    /** @var Collection<int, RouteGenerator> */
    public protected(set) Collection $routeGenerators;

    /** @var array<string, string> Barrel contents keyed by namespace path */
    public protected(set) array $routeModularBarrels = [];

    /** @var Collection<int, FormRequestGenerator> */
    public protected(set) Collection $formRequestGenerators;

    /** @var array<string, string> Barrel contents keyed by namespace path */
    public protected(set) array $formRequestModularBarrels = [];

    public protected(set) string $broadcastChannelsContent = '';

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
     * Build a generator for $fqcn, reusing the cached snapshot when its recorded dependencies are unchanged.
     *
     * @template T of CoreGenerator
     *
     * @param  class-string<T>  $generatorClass
     * @return T
     */
    protected function cachedGenerate(string $fqcn, string $generatorClass): CoreGenerator
    {
        // A custom `*.generator_class` need not use the RehydratesFromCache trait, so guard on fromCache()
        // before a later hit tries to call it.
        if ($this->manifest === null || ! method_exists($generatorClass, 'fromCache')) {
            /** @var T $generator */
            $generator = resolve($generatorClass, ['findable' => $fqcn]);

            return $generator;
        }

        $cacheKey = $generatorClass.'::'.$fqcn;

        // Folds inputs living outside any class file (e.g. route definitions) into the fingerprint.
        $signature = is_subclass_of($generatorClass, ProvidesCacheSignature::class, true)
            ? $generatorClass::cacheSignature($fqcn)
            : '';
        // A model joining or leaving the published set changes what every other class may name.
        $signature .= PublishedModelRegistry::signature();
        $this->manifest->markSeen($cacheKey);

        // Recomputed over the deps recorded on the last build, so editing any of them flips the fingerprint.
        $storedDeps = $this->manifest->deps($cacheKey);

        if ($storedDeps !== [] && $this->manifest->hit($cacheKey, Fingerprinter::fromPaths($storedDeps, $signature))) {
            $snapshot = $this->manifest->snapshot($cacheKey);
            $filename = $this->manifest->filename($cacheKey);

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

        DependencyRecorder::start();
        OutputRecorder::start();

        try {
            DependencyRecorder::recordClass($fqcn);

            /** @var T $generator */
            $generator = resolve($generatorClass, ['findable' => $fqcn]);

            $deps = DependencyRecorder::paths();
            $outputs = OutputRecorder::paths();
        } finally {
            DependencyRecorder::stop();
            OutputRecorder::stop();
        }

        if (! isset($generator->transformer) || ! $generator->transformer instanceof CoreTransformer) {
            return $generator;
        }

        /** @var CoreTransformer<mixed> $transformer */
        $transformer = $generator->transformer;
        try {
            $snapshot = base64_encode(serialize($transformer));
        } catch (Throwable) {
            // Caching is best-effort: a transformer holding a non-serializable value just rebuilds next run.
            return $generator;
        }

        $this->manifest->record(
            $cacheKey,
            Fingerprinter::fromPaths($deps, $signature),
            $generator->filename(),
            $deps,
            $outputs,
            $snapshot,
        );

        return $generator;
    }

    /**
     * The models this run publishes: the collected ones, then each model their relations reach on demand. Registers
     * the set and builds the morph target map over it, so MorphTo relations resolve to precise union types.
     *
     * @return list<class-string>
     */
    protected function buildModelMorphTargetMap(): array
    {
        /** @var object $collector */
        $collector = resolve(Config::string('ts-publish.models.collector_class', ModelsCollector::class));
        $collect = [$collector, 'collect'];

        if (! is_callable($collect)) {
            throw new InvalidArgumentException(
                sprintf('Configured models collector [%s] must offer collect().', $collector::class),
            );
        }

        $resolver = resolve(ModelAttributeResolver::class);
        $withoutTables = ! $this->inspectsModelTables();

        /** @var list<class-string> $collected */
        $collected = Collection::wrap($collect())->all();

        // A project's own collector need only offer collect(); only a CoreCollector can say what it would accept.
        $modelClasses = $collector instanceof CoreCollector
            ? $resolver->withRelatedModels(
                $collected,
                fn (string $class): bool => $collector->accepts($class) && $this->modelTableExists($class),
                $withoutTables,
            )
            : $collected;

        PublishedModelRegistry::register($modelClasses);

        if ($withoutTables) {
            $resolver->buildMorphTargetMapWithoutTables($modelClasses);
        } else {
            $resolver->buildMorphTargetMap($modelClasses);
        }

        return $modelClasses;
    }

    /**
     * Whether building the published set inspects every model's table, as the model phase does anyway.
     */
    protected function inspectsModelTables(): bool
    {
        return true;
    }

    /**
     * Whether a model's table or view exists; a model published on demand needs one, or it would publish no columns.
     *
     * @param  class-string  $class
     */
    protected function modelTableExists(string $class): bool
    {
        try {
            /** @var Model $instance */
            $instance = resolve($class);
            $schema = $instance->getConnection()->getSchemaBuilder();

            return $schema->hasTable($instance->getTable()) || $schema->hasView($instance->getTable());
        } catch (Throwable) {
            return false;
        }
    }
}
