<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Runners;

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaSharedDataAnalyzer;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedResourceRegistry;
use AbeTwoThree\LaravelTsPublish\Collectors\BroadcastChannelsCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\BroadcastEventsCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\EnumsCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\FormRequestsCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\ModelMetadataCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\ResourcesCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\RoutesCollector;
use AbeTwoThree\LaravelTsPublish\Generators\BroadcastEventGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\FormRequestGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelMetadataGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\RouteGenerator;
use AbeTwoThree\LaravelTsPublish\Metadata\ModelMetadataProviderResolver;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Throwable;

class Runner extends BaseRunner
{
    public function run(): void
    {
        // Process-static and only ever added to. Clearing at the run boundary, not next to register(),
        // is what makes "this run publishes no resources" mean an empty registry, not the last run's set.
        PublishedResourceRegistry::reset();
        AnalysisWarnings::reset();

        /** @var BarrelWriter $barrelWriter */
        $barrelWriter = resolve(Config::string('ts-publish.barrel_writer_class', BarrelWriter::class));
        $this->barrelWriter = $barrelWriter;

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

        $this->generateGlobals();
        $this->generateViteEnv();
        $this->generateJson();
        $this->generateWatcherJson();

        $this->manifest?->save();
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
        $this->logger?->success('Enums — '.$this->enumGenerators->count());
    }

    protected function generateModels(): void
    {
        if (! $this->shouldPublishModels) {
            /** @var Collection<int, ModelGenerator> $empty */
            $empty = collect();
            $this->modelGenerators = $empty;

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

        $modelClasses = $this->collectModelMetadataClasses();

        /** @var class-string<ModelMetadataGenerator> $generatorClass */
        $generatorClass = Config::string(
            'ts-publish.model_metadata.generator_class',
            ModelMetadataGenerator::class,
        );
        $this->validateModelMetadataConfiguration($generatorClass);

        /** @var Collection<int, ModelMetadataGenerator> $generators */
        $generators = collect();

        foreach ($modelClasses as $modelClass) {
            try {
                $generators->push($this->cachedGenerate($modelClass, $generatorClass));
            } catch (Throwable $exception) {
                AnalysisWarnings::add($modelClass, $exception::class.': '.$exception->getMessage());
                $this->shouldMergeModelBarrels = true;
            }
        }

        $this->modelMetadataGenerators = $generators;
        $this->logger?->success('Model metadata — '.$generators->count());
    }

    /**
     * Validate metadata services before processing individual models.
     *
     * @param  class-string  $generatorClass
     */
    protected function validateModelMetadataConfiguration(string $generatorClass): void
    {
        resolve(ModelMetadataProviderResolver::class)->resolve();

        if (! is_a($generatorClass, ModelMetadataGenerator::class, true)) {
            throw new InvalidArgumentException(
                "Configured model metadata generator [{$generatorClass}] must extend ".ModelMetadataGenerator::class.'.',
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
        $shouldMerge = $this->shouldMergeModelBarrels
            || $this->shouldPublishModels !== $this->shouldPublishModelMetadata;

        if (! $shouldMerge) {
            $this->modelModularBarrels = $this->barrelWriter->writeModular($generators);

            return;
        }

        if (! $this->barrelWriter->supportsModularMerging()) {
            if (! Config::boolean('ts-publish.output_to_files') || ! $this->modelBarrelExists($generators)) {
                $this->modelModularBarrels = $this->barrelWriter->writeModular($generators);

                return;
            }

            $this->modelModularBarrels = [];
            $this->logger?->warning('Model barrels were not updated because the configured writer does not support merging.');

            return;
        }

        $this->modelModularBarrels = $this->barrelWriter->mergeModular($generators);
    }

    /**
     * Determine whether any generated model namespace already has a barrel file.
     *
     * @param  Collection<int, ModelGenerator|ModelMetadataGenerator>  $generators
     */
    protected function modelBarrelExists(Collection $generators): bool
    {
        $outputDirectory = Config::string('ts-publish.output_directory');

        return $generators->contains(
            fn (ModelGenerator|ModelMetadataGenerator $generator): bool => is_file(
                $outputDirectory.'/'.$generator->transformer->namespacePath.'/index.ts',
            ),
        );
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
}
