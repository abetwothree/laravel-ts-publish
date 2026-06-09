<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Runners;

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaSharedDataAnalyzer;
use AbeTwoThree\LaravelTsPublish\Collectors\BroadcastChannelsCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\BroadcastEventsCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\EnumsCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\FormRequestsCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\ModelsCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\ResourcesCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\RoutesCollector;
use AbeTwoThree\LaravelTsPublish\Generators\BroadcastEventGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\FormRequestGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\RouteGenerator;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
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

class Runner extends BaseRunner
{
    public function run(): void
    {
        /** @var BarrelWriter $barrelWriter */
        $barrelWriter = resolve(config()->string('ts-publish.barrel_writer_class'));
        $this->barrelWriter = $barrelWriter;

        $this->generateEnums();
        $this->generateModels();
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
    }

    protected function generateEnums(): void
    {
        if (! $this->shouldPublishEnums) {
            /** @var Collection<int, EnumGenerator> $empty */
            $empty = collect();
            $this->enumGenerators = $empty;

            return;
        }

        /** @var EnumsCollector $collector */
        $collector = resolve(config()->string('ts-publish.enums.collector_class'));

        /** @var Collection<int, EnumGenerator> $enumGenerators */
        $enumGenerators = collect();

        foreach ($collector->collect() as $enumClass) {
            /** @var EnumGenerator $generator */
            $generator = resolve(
                config()->string('ts-publish.enums.generator_class'),
                ['findable' => $enumClass],
            );

            $enumGenerators->push($generator);
        }

        $this->enumGenerators = $enumGenerators;

        $this->enumModularBarrels = $this->barrelWriter->writeModular($this->enumGenerators);
    }

    protected function generateModels(): void
    {
        if (! $this->shouldPublishModels) {
            /** @var Collection<int, ModelGenerator> $empty */
            $empty = collect();
            $this->modelGenerators = $empty;

            return;
        }

        /** @var ModelsCollector $collector */
        $collector = resolve(config()->string('ts-publish.models.collector_class'));

        /** @var list<class-string> $modelClasses */
        $modelClasses = $collector->collect()->all();

        // Pre-scan all models to build the morph target map so that MorphTo
        // relations can be resolved to precise union types.
        resolve(ModelAttributeResolver::class)->buildMorphTargetMap($modelClasses);

        /** @var Collection<int, ModelGenerator> $modelGenerators */
        $modelGenerators = collect();

        foreach ($modelClasses as $modelClass) {
            /** @var ModelGenerator $generator */
            $generator = resolve(
                config()->string('ts-publish.models.generator_class'),
                ['findable' => $modelClass],
            );

            $modelGenerators->push($generator);
        }

        $this->modelGenerators = $modelGenerators;

        $this->modelModularBarrels = $this->barrelWriter->writeModular($this->modelGenerators);
    }

    protected function generateResources(): void
    {
        if (! $this->shouldPublishResources) {
            /** @var Collection<int, ResourceGenerator> $empty */
            $empty = collect();
            $this->resourceGenerators = $empty;

            return;
        }

        /** @var ResourcesCollector $collector */
        $collector = resolve(config()->string('ts-publish.resources.collector_class'));

        /** @var Collection<int, ResourceGenerator> $resourceGenerators */
        $resourceGenerators = collect();

        foreach ($collector->collect() as $resourceClass) {
            /** @var ResourceGenerator $generator */
            $generator = resolve(
                config()->string('ts-publish.resources.generator_class'),
                ['findable' => $resourceClass],
            );

            $resourceGenerators->push($generator);
        }

        $this->resourceGenerators = $resourceGenerators;

        $this->resourceModularBarrels = $this->barrelWriter->writeModular($this->resourceGenerators);
    }

    /**
     * Generate the inertia module augmentation file.
     *
     * Runs before route generation so Inertia.SharedData is defined
     * before page types reference it.
     */
    protected function generateInertiaConfig(): void
    {
        if (! config()->boolean('ts-publish.inertia.enabled')) {
            return;
        }

        $middlewarePath = config()->get('ts-publish.inertia.inertia_middleware_path');
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
    }

    protected function generateFormRequests(): void
    {
        /** @var Collection<int, FormRequestGenerator> $empty */
        $empty = collect();

        if (! $this->shouldPublishFormRequests || ! config()->boolean('ts-publish.form_requests.enabled')) {
            $this->formRequestGenerators = $empty;

            return;
        }

        /** @var FormRequestsCollector $collector */
        $collector = resolve(config()->string('ts-publish.form_requests.collector_class'));

        /** @var Collection<int, FormRequestGenerator> $formRequestGenerators */
        $formRequestGenerators = collect();

        foreach ($collector->collect() as $formRequestClass) {
            /** @var FormRequestGenerator $generator */
            $generator = resolve(
                config()->string('ts-publish.form_requests.generator_class'),
                ['findable' => $formRequestClass],
            );

            $formRequestGenerators->push($generator);
        }

        $this->formRequestGenerators = $formRequestGenerators;

        $formRequestOutputPath = config('ts-publish.form_requests.output_path');
        $this->formRequestModularBarrels = $this->barrelWriter->writeModular(
            $this->formRequestGenerators,
            is_string($formRequestOutputPath) ? $formRequestOutputPath : null,
        );
    }

    protected function generateBroadcastChannels(): void
    {
        if (! $this->shouldPublishBroadcastChannels || ! config()->boolean('ts-publish.broadcast_channels.enabled')) {
            $this->broadcastChannelsContent = '';

            return;
        }

        /** @var BroadcastChannelsCollector $collector */
        $collector = resolve(config()->string('ts-publish.broadcast_channels.collector_class'));

        /** @var BroadcastChannelsWriter $writer */
        $writer = resolve(config()->string('ts-publish.broadcast_channels.writer_class'));

        $this->broadcastChannelsContent = $writer->write($collector->collect());
    }

    /**
     * Collect, transform, and write all broadcast event TypeScript interface files.
     *
     * Skips when shouldPublishBroadcastEvents is false or broadcast_events is disabled in config.
     * Writes per-event files, barrel index files, the broadcast-events.ts index, and the
     * optional echo-broadcast-events.d.ts module augmentation file.
     */
    protected function generateBroadcastEvents(): void
    {
        /** @var Collection<int, BroadcastEventGenerator> $empty */
        $empty = collect();

        if (! $this->shouldPublishBroadcastEvents || ! config()->boolean('ts-publish.broadcast_events.enabled')) {
            $this->broadcastEventGenerators = $empty;
            $this->broadcastEventModularBarrels = [];
            $this->broadcastEventsIndexContent = '';
            $this->broadcastEventsEchoContent = '';

            return;
        }

        /** @var BroadcastEventsCollector $collector */
        $collector = resolve(config()->string('ts-publish.broadcast_events.collector_class'));

        /** @var Collection<int, BroadcastEventGenerator> $broadcastEventGenerators */
        $broadcastEventGenerators = collect();

        foreach ($collector->collect() as $eventClass) {
            /** @var BroadcastEventGenerator $generator */
            $generator = resolve(
                config()->string('ts-publish.broadcast_events.generator_class'),
                ['findable' => $eventClass],
            );

            $broadcastEventGenerators->push($generator);
        }

        $this->broadcastEventGenerators = $broadcastEventGenerators;

        $broadcastEventsOutputPath = config('ts-publish.broadcast_events.output_path');
        $this->broadcastEventModularBarrels = $this->barrelWriter->writeModular(
            $this->broadcastEventGenerators,
            is_string($broadcastEventsOutputPath) ? $broadcastEventsOutputPath : null,
        );

        /** @var BroadcastEventsIndexWriter $indexWriter */
        $indexWriter = resolve(config()->string('ts-publish.broadcast_events.index_writer_class'));
        $this->broadcastEventsIndexContent = $indexWriter->write($this->broadcastEventGenerators);

        /** @var BroadcastEventsEchoWriter $echoWriter */
        $echoWriter = resolve(config()->string('ts-publish.broadcast_events.echo_augmentation.writer_class'));
        $this->broadcastEventsEchoContent = $echoWriter->write($this->broadcastEventGenerators);
    }

    protected function generateRoutes(): void
    {
        /** @var Collection<int, RouteGenerator> $empty */
        $empty = collect();

        if (! $this->shouldPublishRoutes || ! config()->boolean('ts-publish.routes.enabled')) {
            $this->routeGenerators = $empty;

            return;
        }

        /** @var RoutesCollector $collector */
        $collector = resolve(config()->string('ts-publish.routes.collector_class'));

        /** @var Collection<int, RouteGenerator> $routeGenerators */
        $routeGenerators = collect();

        foreach ($collector->collect() as $controllerClass) {
            /** @var RouteGenerator $generator */
            $generator = resolve(
                config()->string('ts-publish.routes.generator_class'),
                ['findable' => $controllerClass],
            );

            $routeGenerators->push($generator);
        }

        $this->routeGenerators = $routeGenerators;

        /** @var RouteWriter $routeWriter */
        $routeWriter = resolve(config()->string('ts-publish.routes.writer_class'));

        $this->routeModularBarrels = $routeWriter->writeRouteBarrels($this->routeGenerators);
    }

    protected function generateGlobals(): void
    {
        /** @var GlobalsWriter $globalsWriter */
        $globalsWriter = resolve(config()->string('ts-publish.globals.writer_class'));
        $this->globalsWriter = $globalsWriter;

        $this->globalsContent = $globalsWriter->write($this);
    }

    protected function generateJson(): void
    {
        /** @var JsonWriter $jsonWriter */
        $jsonWriter = resolve(config()->string('ts-publish.json.writer_class'));

        $this->jsonContent = $jsonWriter->write($this);
    }

    /**
     * Generate the vite-env.d.ts declaration file for VITE_-prefixed environment variables.
     */
    protected function generateViteEnv(): void
    {
        /** @var ViteEnvWriter $writer */
        $writer = resolve(ViteEnvWriter::class);

        $this->viteEnvContent = $writer->write();
    }

    protected function generateWatcherJson(): void
    {
        /** @var WatcherJsonWriter $jsonWriter */
        $jsonWriter = resolve(config()->string('ts-publish.watcher.writer_class'));

        $this->watcherJsonContent = $jsonWriter->write();
    }
}
