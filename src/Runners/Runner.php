<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Runners;

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaSharedDataAnalyzer;
use AbeTwoThree\LaravelTsPublish\Cache\GenerationManifest;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedResourceRegistry;
use AbeTwoThree\LaravelTsPublish\Collectors\BroadcastChannelsCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\BroadcastEventsCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\CoreCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\EnumsCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\FormRequestsCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\ModelMetadataCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\ResourcesCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\RoutesCollector;
use AbeTwoThree\LaravelTsPublish\Generators\BroadcastEventGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\CoreGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\FormRequestGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelMetadataGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\RouteGenerator;
use AbeTwoThree\LaravelTsPublish\Metadata\ModelMetadataProviderResolver;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelMetadataTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\BarrelWriter;
use AbeTwoThree\LaravelTsPublish\Writers\BroadcastChannelsWriter;
use AbeTwoThree\LaravelTsPublish\Writers\BroadcastEventsEchoWriter;
use AbeTwoThree\LaravelTsPublish\Writers\BroadcastEventsIndexWriter;
use AbeTwoThree\LaravelTsPublish\Writers\GlobalsWriter;
use AbeTwoThree\LaravelTsPublish\Writers\InertiaConfigWriter;
use AbeTwoThree\LaravelTsPublish\Writers\JsonWriter;
use AbeTwoThree\LaravelTsPublish\Writers\RouteWriter;
use AbeTwoThree\LaravelTsPublish\Writers\ViteEnvWriter;
use AbeTwoThree\LaravelTsPublish\Writers\WatcherJsonWriter;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Override;
use Throwable;

class Runner extends BaseRunner
{
    /**
     * Each feature a flag skipped while config enables it, rehydrated from its kept entries for the globals and JSON
     * files only; empty unless the cache is on and one of those files is.
     *
     * @var Collection<int, EnumGenerator>
     */
    public protected(set) Collection $retainedEnumGenerators;

    /** @var Collection<int, ModelGenerator> */
    public protected(set) Collection $retainedModelGenerators;

    /** @var Collection<int, ResourceGenerator> */
    public protected(set) Collection $retainedResourceGenerators;

    /** @var Collection<int, FormRequestGenerator> */
    public protected(set) Collection $retainedFormRequestGenerators;

    /** @var Collection<int, BroadcastEventGenerator> */
    public protected(set) Collection $retainedBroadcastEventGenerators;

    /** @var list<class-string>|null The published set a skipped model phase built, so retaining reuses it. */
    private ?array $skippedModelSet = null;

    /**
     * Start with no retained generators, so a runner that never ran answers empty collections.
     */
    public function __construct()
    {
        $this->forgetRetainedGenerators();
    }

    public function run(): void
    {
        $this->resetRunState();

        /** @var BarrelWriter $barrelWriter */
        $barrelWriter = resolve(Config::string('ts-publish.barrel_writer_class', BarrelWriter::class));
        $this->barrelWriter = $barrelWriter;

        // Validated before any file is written, so a broken provider fails before the run leaves a half-built tree.
        if ($this->shouldPublishModelMetadata) {
            $this->validateModelMetadataConfiguration();
        } elseif ($this->shouldPublishModels) {
            $this->validateModelMetadataTransformer();
        }

        $this->generateEnums();
        $this->generateModels();
        $this->generateModelMetadata();
        $this->generateModelBarrels();
        $this->generateResources();
        $this->generateInertiaConfig();
        $this->generateFormRequests();
        $this->generateBroadcastChannels();
        $this->generateBroadcastEvents();
        $this->generateRoutes();

        $this->retainSkippedGenerators();
        $this->generateGlobals();
        $this->generateViteEnv();
        $this->generateJson();
        $this->generateWatcherJson();

        $manifest = $this->manifest;

        if ($manifest !== null) {
            $this->keepSkippedFeatureEntries($manifest);
            $manifest->save();
        }
    }

    /**
     * Clear the run state, including the generators an earlier run retained from skipped features.
     */
    protected function resetRunState(): void
    {
        parent::resetRunState();

        $this->forgetRetainedGenerators();
        $this->skippedModelSet = null;
    }

    /**
     * Keep the cache entries of each feature a flag skipped while config enables it: its files stay published, so
     * the next run that publishes it can still hit. A feature disabled in config keeps nothing, so it is pruned.
     */
    protected function keepSkippedFeatureEntries(GenerationManifest $manifest): void
    {
        foreach ($this->skippedFeatureGenerators() as $generatorClass) {
            $manifest->keepEntriesOf($generatorClass);
        }
    }

    /**
     * Rehydrate each feature a flag skipped while config enables it from its kept entries, in collector order, so the
     * globals and JSON files list it as the last run that published it left it. A class with no usable entry drops out.
     */
    protected function retainSkippedGenerators(): void
    {
        $skipped = $this->manifest !== null
            && (Config::boolean('ts-publish.globals.enabled') || Config::boolean('ts-publish.json.enabled'))
                ? $this->skippedFeatureGenerators()
                : [];

        /** @var class-string<EnumGenerator>|null $enums */
        $enums = $skipped['enums'] ?? null;
        /** @var class-string<ModelGenerator>|null $models */
        $models = $skipped['models'] ?? null;
        /** @var class-string<ResourceGenerator>|null $resources */
        $resources = $skipped['resources'] ?? null;
        /** @var class-string<FormRequestGenerator>|null $formRequests */
        $formRequests = $skipped['form_requests'] ?? null;
        /** @var class-string<BroadcastEventGenerator>|null $broadcastEvents */
        $broadcastEvents = $skipped['broadcast_events'] ?? null;

        $this->retainedEnumGenerators = $this->rehydrateAll(
            $enums,
            fn (): Collection => $this->collected('enums', EnumsCollector::class),
        );
        $this->retainedModelGenerators = $this->rehydrateAll(
            $models,
            fn (): array => $this->skippedModelSet ?? $this->buildModelMorphTargetMap(),
        );
        $this->retainedResourceGenerators = $this->rehydrateAll(
            $resources,
            fn (): Collection => $this->collected('resources', ResourcesCollector::class),
        );
        $this->retainedFormRequestGenerators = $this->rehydrateAll(
            $formRequests,
            fn (): Collection => $this->collected('form_requests', FormRequestsCollector::class),
        );
        $this->retainedBroadcastEventGenerators = $this->rehydrateAll(
            $broadcastEvents,
            fn (): Collection => $this->collected('broadcast_events', BroadcastEventsCollector::class),
        );
    }

    protected function generateEnums(): void
    {
        if (! $this->shouldPublishEnums) {
            /** @var Collection<int, EnumGenerator> $empty */
            $empty = collect();
            $this->enumGenerators = $empty;

            return;
        }

        $this->logger?->subLabel('Enums…');

        /** @var EnumsCollector $collector */
        $collector = resolve(Config::string('ts-publish.enums.collector_class', EnumsCollector::class));

        /** @var Collection<int, EnumGenerator> $enumGenerators */
        $enumGenerators = collect();

        foreach ($collector->collect() as $enumClass) {
            /** @var class-string<EnumGenerator> $generatorClass */
            $generatorClass = Config::string('ts-publish.enums.generator_class', EnumGenerator::class);

            $enumGenerators->push($this->cachedGenerate($enumClass, $generatorClass));
        }

        $this->enumGenerators = $enumGenerators;

        $this->enumModularBarrels = $this->barrelWriter->writeModular($this->enumGenerators);
        $this->enumGenerators->each($this->warnOfReindexedEnumValues(...));
        $this->warnOfCollidingEnumNames();
        $this->logger?->success('Enums — '.$this->enumGenerators->count());
    }

    /**
     * Warn when two enums in one namespace publish the same name: `Role` publishes the type `RoleType`, which an enum
     * named `RoleType` publishes as its const, and one barrel or global namespace cannot export both.
     */
    protected function warnOfCollidingEnumNames(): void
    {
        /** @var array<string, array<string, string>> $publishers namespace path => published name => enum FQCN */
        $publishers = [];

        foreach ($this->enumGenerators as $generator) {
            $transformer = $generator->transformer;
            $names = [$transformer->enumName, $transformer->enumName.'Type'];

            if ($transformer->backed) {
                $names[] = $transformer->enumName.'Kind';
            }

            foreach ($names as $name) {
                $publisher = $publishers[$transformer->namespacePath][$name] ?? null;

                if ($publisher === null) {
                    $publishers[$transformer->namespacePath][$name] = $transformer->fqcn();

                    continue;
                }

                AnalysisWarnings::add($transformer->fqcn(), sprintf(
                    'Publishes the name [%s], which [%s] also publishes in the same namespace, so their barrel and the globals file do not compile. Give one enum another name with #[TsEnum].',
                    $name,
                    $publisher,
                ));
            }
        }
    }

    protected function generateModels(): void
    {
        if (! $this->shouldPublishModels) {
            /** @var Collection<int, ModelGenerator> $empty */
            $empty = collect();
            $this->modelGenerators = $empty;

            // A flag skipped the phase, but the model files stay published, so a later phase reads the same set.
            $this->skippedModelSet = Config::boolean('ts-publish.models.enabled', false) && $this->publishesAfterModels()
                ? $this->buildModelMorphTargetMap()
                : null;

            return;
        }

        $this->logger?->subLabel('Models…');

        /** @var list<class-string> $modelClasses */
        $modelClasses = $this->buildModelMorphTargetMap();

        /** @var Collection<int, ModelGenerator> $modelGenerators */
        $modelGenerators = collect();

        foreach ($modelClasses as $modelClass) {
            /** @var class-string<ModelGenerator> $generatorClass */
            $generatorClass = Config::string('ts-publish.models.generator_class', ModelGenerator::class);

            $modelGenerators->push($this->cachedGenerate($modelClass, $generatorClass));
        }

        $this->modelGenerators = $modelGenerators;
        $this->logger?->success('Models — '.$this->modelGenerators->count());
    }

    /**
     * Whether a phase after the model phase runs: each can name a model, and each caches under the published set.
     * The Inertia config always runs when it is on, and types the shared data through the same engine.
     */
    protected function publishesAfterModels(): bool
    {
        return $this->shouldPublishModelMetadata || $this->shouldPublishResources || $this->shouldPublishRoutes
            || $this->shouldPublishFormRequests || $this->shouldPublishBroadcastEvents
            || Config::boolean('ts-publish.inertia.enabled');
    }

    /**
     * Only a run that generates the models reads their tables, so any other builds the published set from relations.
     */
    #[Override]
    protected function inspectsModelTables(): bool
    {
        return $this->shouldPublishModels;
    }

    /**
     * Generate runtime metadata independently from model interfaces.
     */
    protected function generateModelMetadata(): void
    {
        if (! $this->shouldPublishModelMetadata) {
            /** @var Collection<int, ModelMetadataGenerator> $empty */
            $empty = collect();
            $this->modelMetadataGenerators = $empty;

            return;
        }

        $this->logger?->subLabel('Model metadata…');

        /** @var class-string<ModelMetadataGenerator> $generatorClass */
        $generatorClass = Config::string(
            'ts-publish.model_metadata.generator_class',
            ModelMetadataGenerator::class,
        );

        /** @var Collection<int, ModelMetadataGenerator> $generators */
        $generators = collect();

        foreach ($this->collectModelMetadataClasses() as $modelClass) {
            try {
                $generators->push($this->cachedGenerate($modelClass, $generatorClass));
            } catch (Throwable $exception) {
                $this->modelMetadataFailures[] = [
                    'subject' => $modelClass,
                    'message' => $exception::class.': '.$exception->getMessage(),
                ];
            }
        }

        $this->modelMetadataGenerators = $generators;
        $this->logger?->success('Model metadata — '.$generators->count());
    }

    /**
     * Validate the configured metadata provider, generator, and transformer before any model is processed.
     */
    protected function validateModelMetadataConfiguration(): void
    {
        resolve(ModelMetadataProviderResolver::class)->resolve();

        $generatorClass = Config::string('ts-publish.model_metadata.generator_class', ModelMetadataGenerator::class);

        if (! is_a($generatorClass, ModelMetadataGenerator::class, true)) {
            throw new InvalidArgumentException(
                "Configured model metadata generator [{$generatorClass}] must extend ".ModelMetadataGenerator::class.'.',
            );
        }

        $this->validateModelMetadataTransformer();
    }

    /**
     * Validate the transformer class the barrel phase static-dispatches through.
     *
     * Checked on every run that touches a model barrel, not only on runs that publish metadata: a run that
     * skips the metadata phase still asks the configured transformer which exports that phase owns.
     */
    protected function validateModelMetadataTransformer(): void
    {
        $transformerClass = Config::string(
            'ts-publish.model_metadata.transformer_class',
            ModelMetadataTransformer::class,
        );

        if (! is_a($transformerClass, ModelMetadataTransformer::class, true)) {
            throw new InvalidArgumentException(
                "Configured model metadata transformer [{$transformerClass}] must extend "
                .ModelMetadataTransformer::class.'.',
            );
        }
    }

    /**
     * Collect model classes configured for metadata publishing.
     *
     * @return list<class-string>
     */
    protected function collectModelMetadataClasses(): array
    {
        /** @var ModelMetadataCollector $collector */
        $collector = resolve(Config::string(
            'ts-publish.model_metadata.collector_class',
            ModelMetadataCollector::class,
        ));

        return array_values($collector->collect()->all());
    }

    /**
     * Write the barrel files shared by models and their metadata companions.
     */
    protected function generateModelBarrels(): void
    {
        $generators = $this->modelGenerators->concat($this->modelMetadataGenerators);
        $keepExisting = $this->preservedModelBarrelExports();

        $this->modelModularBarrels = $keepExisting === null
            ? $this->barrelWriter->writeModular($generators)
            : $this->barrelWriter->writeModularPreserving($generators, $keepExisting);
    }

    /**
     * Decide which existing model-barrel exports outlive this run; null rebuilds every barrel from scratch.
     *
     * A phase enabled in config but skipped this run keeps its exports; a model whose metadata failed keeps its
     * last-known-good companion export. A phase disabled in config keeps nothing, so turning it off prunes it.
     *
     * @return (Closure(string): bool)|null
     */
    protected function preservedModelBarrelExports(): ?Closure
    {
        $keepModels = ! $this->shouldPublishModels && Config::boolean('ts-publish.models.enabled', false);
        $keepMetadata = ! $this->shouldPublishModelMetadata
            && Config::boolean('ts-publish.model_metadata.enabled', false);

        // The configured transformer names the companion files, so only it can say which exports the phase owns.
        /** @var class-string<ModelMetadataTransformer> $transformerClass */
        $transformerClass = Config::string(
            'ts-publish.model_metadata.transformer_class',
            ModelMetadataTransformer::class,
        );

        $failed = array_map(
            static fn (array $failure): string => $transformerClass::filenameFor($failure['subject']),
            $this->modelMetadataFailures,
        );

        if (! $keepModels && ! $keepMetadata && $failed === []) {
            return null;
        }

        return static fn (string $filename): bool => $transformerClass::isMetadataFilename($filename)
            ? $keepMetadata || in_array($filename, $failed, true)
            : $keepModels;
    }

    protected function generateResources(): void
    {
        if (! $this->shouldPublishResources) {
            /** @var Collection<int, ResourceGenerator> $empty */
            $empty = collect();
            $this->resourceGenerators = $empty;

            return;
        }

        $this->logger?->subLabel('Resources…');

        /** @var ResourcesCollector $collector */
        $collector = resolve(Config::string('ts-publish.resources.collector_class', ResourcesCollector::class));

        /** @var Collection<int, ResourceGenerator> $resourceGenerators */
        $resourceGenerators = collect();

        // Registered up front, not as each generator completes: a resource analyzed on the first
        // iteration may reference one collected on the last, so resolution must be order-independent.
        $collected = $collector->collect();

        PublishedResourceRegistry::register($collected);

        foreach ($collected as $resourceClass) {
            /** @var class-string<ResourceGenerator> $generatorClass */
            $generatorClass = Config::string('ts-publish.resources.generator_class', ResourceGenerator::class);

            $resourceGenerators->push($this->cachedGenerate($resourceClass, $generatorClass));
        }

        $this->resourceGenerators = $resourceGenerators;

        $this->resourceModularBarrels = $this->barrelWriter->writeModular($this->resourceGenerators);
        $this->logger?->success('Resources — '.$this->resourceGenerators->count());
    }

    /**
     * Generate the inertia module augmentation file.
     *
     * Runs before route generation so Inertia.SharedData is defined before page types reference it.
     */
    protected function generateInertiaConfig(): void
    {
        if (! Config::boolean('ts-publish.inertia.enabled')) {
            return;
        }

        $this->logger?->subLabel('Inertia config…');

        $middlewarePath = Config::get('ts-publish.inertia.inertia_middleware_path');
        if (! is_string($middlewarePath) || ! is_dir($middlewarePath)) {
            $middlewarePath = app_path();
        }

        /** @var InertiaSharedDataAnalyzer $analyzer */
        $analyzer = resolve(InertiaSharedDataAnalyzer::class);
        $analyzer->setAppPaths($middlewarePath);

        $sharedData = $analyzer->analyze();

        if ($sharedData === null) {
            return;
        }

        /** @var InertiaConfigWriter $writer */
        $writer = resolve(InertiaConfigWriter::class);

        $this->inertiaConfigContent = $writer->write($sharedData);
        $this->logger?->success('Inertia config');
    }

    protected function generateFormRequests(): void
    {
        /** @var Collection<int, FormRequestGenerator> $empty */
        $empty = collect();

        if (! $this->shouldPublishFormRequests || ! Config::boolean('ts-publish.form_requests.enabled')) {
            $this->formRequestGenerators = $empty;

            return;
        }

        $this->logger?->subLabel('Form requests…');

        /** @var FormRequestsCollector $collector */
        $collector = resolve(Config::string('ts-publish.form_requests.collector_class', FormRequestsCollector::class));

        /** @var Collection<int, FormRequestGenerator> $formRequestGenerators */
        $formRequestGenerators = collect();

        foreach ($collector->collect() as $formRequestClass) {
            /** @var class-string<FormRequestGenerator> $generatorClass */
            $generatorClass = Config::string('ts-publish.form_requests.generator_class', FormRequestGenerator::class);

            $formRequestGenerators->push($this->cachedGenerate($formRequestClass, $generatorClass));
        }

        $this->formRequestGenerators = $formRequestGenerators;

        $formRequestOutputPath = Config::get('ts-publish.form_requests.output_directory');
        $this->formRequestModularBarrels = $this->barrelWriter->writeModular(
            $this->formRequestGenerators,
            is_string($formRequestOutputPath) ? $formRequestOutputPath : null,
        );
        $this->logger?->success('Form requests — '.$this->formRequestGenerators->count());
    }

    protected function generateBroadcastChannels(): void
    {
        if (! $this->shouldPublishBroadcastChannels || ! Config::boolean('ts-publish.broadcast_channels.enabled')) {
            $this->broadcastChannelsContent = '';

            return;
        }

        $this->logger?->subLabel('Broadcast channels…');

        /** @var BroadcastChannelsCollector $collector */
        $collector = resolve(Config::string('ts-publish.broadcast_channels.collector_class', BroadcastChannelsCollector::class));

        /** @var BroadcastChannelsWriter $writer */
        $writer = resolve(Config::string('ts-publish.broadcast_channels.writer_class', BroadcastChannelsWriter::class));

        $this->broadcastChannelsContent = $writer->write($collector->collect());
        $this->logger?->success('Broadcast channels');
    }

    /**
     * Collect, transform, and write the broadcast event files, their barrels, index, and echo augmentation.
     */
    protected function generateBroadcastEvents(): void
    {
        /** @var Collection<int, BroadcastEventGenerator> $empty */
        $empty = collect();

        if (! $this->shouldPublishBroadcastEvents || ! Config::boolean('ts-publish.broadcast_events.enabled')) {
            $this->broadcastEventGenerators = $empty;
            $this->broadcastEventModularBarrels = [];
            $this->broadcastEventsIndexContent = '';
            $this->broadcastEventsEchoContent = '';

            return;
        }

        $this->logger?->subLabel('Broadcast events…');

        /** @var BroadcastEventsCollector $collector */
        $collector = resolve(Config::string('ts-publish.broadcast_events.collector_class', BroadcastEventsCollector::class));

        /** @var Collection<int, BroadcastEventGenerator> $broadcastEventGenerators */
        $broadcastEventGenerators = collect();

        foreach ($collector->collect() as $eventClass) {
            /** @var class-string<BroadcastEventGenerator> $generatorClass */
            $generatorClass = Config::string('ts-publish.broadcast_events.generator_class', BroadcastEventGenerator::class);

            $broadcastEventGenerators->push($this->cachedGenerate($eventClass, $generatorClass));
        }

        $this->broadcastEventGenerators = $broadcastEventGenerators;

        $broadcastEventsOutputPath = Config::get('ts-publish.broadcast_events.output_directory');
        $this->broadcastEventModularBarrels = $this->barrelWriter->writeModular(
            $this->broadcastEventGenerators,
            is_string($broadcastEventsOutputPath) ? $broadcastEventsOutputPath : null,
        );

        /** @var BroadcastEventsIndexWriter $indexWriter */
        $indexWriter = resolve(Config::string('ts-publish.broadcast_events.index_writer_class', BroadcastEventsIndexWriter::class));
        $this->broadcastEventsIndexContent = $indexWriter->write($this->broadcastEventGenerators);

        /** @var BroadcastEventsEchoWriter $echoWriter */
        $echoWriter = resolve(Config::string('ts-publish.broadcast_events.echo_augmentation.writer_class', BroadcastEventsEchoWriter::class));
        $this->broadcastEventsEchoContent = $echoWriter->write($this->broadcastEventGenerators);
        $this->logger?->success('Broadcast events — '.$this->broadcastEventGenerators->count());
    }

    protected function generateRoutes(): void
    {
        /** @var Collection<int, RouteGenerator> $empty */
        $empty = collect();

        if (! $this->shouldPublishRoutes || ! Config::boolean('ts-publish.routes.enabled')) {
            $this->routeGenerators = $empty;

            return;
        }

        $this->logger?->subLabel('Route controllers…');

        /** @var RoutesCollector $collector */
        $collector = resolve(Config::string('ts-publish.routes.collector_class', RoutesCollector::class));

        /** @var Collection<int, RouteGenerator> $routeGenerators */
        $routeGenerators = collect();

        foreach ($collector->collect() as $controllerClass) {
            /** @var class-string<RouteGenerator> $generatorClass */
            $generatorClass = Config::string('ts-publish.routes.generator_class', RouteGenerator::class);

            $routeGenerators->push($this->cachedGenerate($controllerClass, $generatorClass));
        }

        $this->routeGenerators = $routeGenerators;

        /** @var RouteWriter $routeWriter */
        $routeWriter = resolve(Config::string('ts-publish.routes.writer_class', RouteWriter::class));

        $this->routeModularBarrels = $routeWriter->writeRouteBarrels($this->routeGenerators);
        $this->logger?->success('Route controllers — '.$this->routeGenerators->count());
    }

    protected function generateGlobals(): void
    {
        $this->logger?->subLabel('Globals…');
        /** @var GlobalsWriter $globalsWriter */
        $globalsWriter = resolve(Config::string('ts-publish.globals.writer_class', GlobalsWriter::class));
        $this->globalsWriter = $globalsWriter;

        $this->globalsContent = $globalsWriter->write($this);
        if ($this->globalsContent !== '') {
            $this->logger?->success('Globals');
        }
    }

    protected function generateJson(): void
    {
        $this->logger?->subLabel('JSON…');
        /** @var JsonWriter $jsonWriter */
        $jsonWriter = resolve(Config::string('ts-publish.json.writer_class', JsonWriter::class));

        $this->jsonContent = $jsonWriter->write($this);
        if ($this->jsonContent !== '') {
            $this->logger?->success('JSON');
        }
    }

    /**
     * Generate the vite-env.d.ts declaration file for VITE_-prefixed environment variables.
     */
    protected function generateViteEnv(): void
    {
        $this->logger?->subLabel('Vite env…');
        /** @var ViteEnvWriter $writer */
        $writer = resolve(ViteEnvWriter::class);

        $this->viteEnvContent = $writer->write();
        if ($this->viteEnvContent !== '') {
            $this->logger?->success('Vite env');
        }
    }

    protected function generateWatcherJson(): void
    {
        $this->logger?->subLabel('Watcher JSON…');
        /** @var WatcherJsonWriter $jsonWriter */
        $jsonWriter = resolve(Config::string('ts-publish.watcher.writer_class', WatcherJsonWriter::class));

        $this->watcherJsonContent = $jsonWriter->write();
        if ($this->watcherJsonContent !== '') {
            $this->logger?->success('Watcher JSON');
        }
    }

    /**
     * The configured generator class of each cached feature a flag skipped while config enables it, keyed by feature.
     *
     * @return array<string, string>
     */
    private function skippedFeatureGenerators(): array
    {
        $features = [
            'enums' => [$this->shouldPublishEnums, EnumGenerator::class],
            'models' => [$this->shouldPublishModels, ModelGenerator::class],
            'model_metadata' => [$this->shouldPublishModelMetadata, ModelMetadataGenerator::class],
            'resources' => [$this->shouldPublishResources, ResourceGenerator::class],
            'routes' => [$this->shouldPublishRoutes, RouteGenerator::class],
            'form_requests' => [$this->shouldPublishFormRequests, FormRequestGenerator::class],
            'broadcast_events' => [$this->shouldPublishBroadcastEvents, BroadcastEventGenerator::class],
        ];

        $skipped = [];

        foreach ($features as $feature => [$published, $defaultGenerator]) {
            if (! $published && Config::boolean("ts-publish.{$feature}.enabled", false)) {
                $skipped[$feature] = Config::string("ts-publish.{$feature}.generator_class", $defaultGenerator);
            }
        }

        return $skipped;
    }

    /**
     * Each class's cached generator, in the order given; none when the feature was not skipped.
     *
     * @template T of CoreGenerator
     *
     * @param  class-string<T>|null  $generatorClass  null when this run did not skip the feature
     * @param  Closure(): iterable<class-string>  $classes
     * @return Collection<int, T>
     */
    private function rehydrateAll(?string $generatorClass, Closure $classes): Collection
    {
        /** @var Collection<int, T> $generators */
        $generators = collect();

        if ($generatorClass === null) {
            return $generators;
        }

        foreach ($classes() as $fqcn) {
            $generator = $this->rehydrate($generatorClass, $fqcn);

            if ($generator !== null) {
                $generators->push($generator);
            }
        }

        return $generators;
    }

    /**
     * The classes a feature's configured collector collects.
     *
     * @return Collection<int, class-string<object>>
     */
    private function collected(string $feature, string $defaultCollector): Collection
    {
        /** @var CoreCollector<object> $collector */
        $collector = resolve(Config::string("ts-publish.{$feature}.collector_class", $defaultCollector));

        return $collector->collect();
    }

    /**
     * Empty the five retained collections.
     */
    private function forgetRetainedGenerators(): void
    {
        /** @var Collection<int, EnumGenerator> $enums */
        $enums = collect();
        $this->retainedEnumGenerators = $enums;

        /** @var Collection<int, ModelGenerator> $models */
        $models = collect();
        $this->retainedModelGenerators = $models;

        /** @var Collection<int, ResourceGenerator> $resources */
        $resources = collect();
        $this->retainedResourceGenerators = $resources;

        /** @var Collection<int, FormRequestGenerator> $formRequests */
        $formRequests = collect();
        $this->retainedFormRequestGenerators = $formRequests;

        /** @var Collection<int, BroadcastEventGenerator> $broadcastEvents */
        $broadcastEvents = collect();
        $this->retainedBroadcastEventGenerators = $broadcastEvents;
    }
}
