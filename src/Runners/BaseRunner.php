<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Runners;

use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Cache\Fingerprinter;
use AbeTwoThree\LaravelTsPublish\Cache\GenerationManifest;
use AbeTwoThree\LaravelTsPublish\Cache\OutputRecorder;
use AbeTwoThree\LaravelTsPublish\Generators\BroadcastEventGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\CoreGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\FormRequestGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\RouteGenerator;
use AbeTwoThree\LaravelTsPublish\Transformers\CoreTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\BarrelWriter;
use AbeTwoThree\LaravelTsPublish\Writers\GlobalsWriter;
use Illuminate\Support\Collection;
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

    abstract public function run(): void;

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
        // No cache (disabled or --source): build normally, no recording.
        if ($this->manifest === null) {
            /** @var T $generator */
            $generator = resolve($generatorClass, ['findable' => $fqcn]);

            return $generator;
        }

        $this->manifest->markSeen($fqcn);

        // HIT: recompute the fingerprint over the previously recorded deps.
        $storedDeps = $this->manifest->deps($fqcn);

        if ($storedDeps !== [] && $this->manifest->hit($fqcn, Fingerprinter::fromPaths($storedDeps))) {
            $snapshot = $this->manifest->snapshot($fqcn);
            $filename = $this->manifest->filename($fqcn);

            if ($snapshot !== null && $filename !== null) {
                // Snapshot is our own trusted cache payload (optionally HMAC-signed
                // by the file backend); it restores transformer objects, so classes
                // must be allowed here (unlike the file backend's array payloads).
                /** @var CoreTransformer<mixed> $transformer */
                $transformer = unserialize(base64_decode($snapshot));

                /** @var T $generator */
                $generator = $generatorClass::fromCache($fqcn, $transformer, $filename); // @phpstan-ignore staticMethod.notFound

                return $generator;
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

        /** @var CoreTransformer<mixed> $transformer */
        $transformer = $generator->transformer; // @phpstan-ignore property.notFound

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
            Fingerprinter::fromPaths($deps),
            $generator->filename(),
            $deps,
            $outputs,
            $snapshot,
        );

        return $generator;
    }
}
