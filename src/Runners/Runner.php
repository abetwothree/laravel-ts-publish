<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Runners;

use AbeTwoThree\LaravelTsPublish\Collectors\EnumsCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\ModelsCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\ResourcesCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\RoutesCollector;
use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\RouteGenerator;
use AbeTwoThree\LaravelTsPublish\Writers\BarrelWriter;
use AbeTwoThree\LaravelTsPublish\Writers\GlobalsWriter;
use AbeTwoThree\LaravelTsPublish\Writers\JsonWriter;
use AbeTwoThree\LaravelTsPublish\Writers\RouteWriter;
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
        $this->generateRoutes();

        $this->generateGlobals();
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
        $collector = resolve(config()->string('ts-publish.enum_collector_class'));

        /** @var Collection<int, EnumGenerator> $enumGenerators */
        $enumGenerators = collect();

        foreach ($collector->collect() as $enumClass) {
            /** @var EnumGenerator $generator */
            $generator = resolve(
                config()->string('ts-publish.enum_generator_class'),
                ['findable' => $enumClass],
            );

            $enumGenerators->push($generator);
        }

        $this->enumGenerators = $enumGenerators;

        if (config()->boolean('ts-publish.modular_publishing')) {
            $this->enumModularBarrels = $this->barrelWriter->writeModular($this->enumGenerators);
            $this->enumBarrelContent = implode("\n\n", $this->enumModularBarrels);
        } else {
            $this->enumBarrelContent = $this->barrelWriter->write(
                $this->enumGenerators,
                'index',
                'enums'
            );
        }
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
        $collector = resolve(config()->string('ts-publish.model_collector_class'));

        /** @var Collection<int, ModelGenerator> $modelGenerators */
        $modelGenerators = collect();

        foreach ($collector->collect() as $modelClass) {
            /** @var ModelGenerator $generator */
            $generator = resolve(
                config()->string('ts-publish.model_generator_class'),
                ['findable' => $modelClass],
            );

            $modelGenerators->push($generator);
        }

        $this->modelGenerators = $modelGenerators;

        if (config()->boolean('ts-publish.modular_publishing')) {
            $this->modelModularBarrels = $this->barrelWriter->writeModular($this->modelGenerators);
            $this->modelBarrelContent = implode("\n\n", $this->modelModularBarrels);
        } else {
            $this->modelBarrelContent = $this->barrelWriter->write(
                $this->modelGenerators,
                'index',
                'models'
            );
        }
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
        $collector = resolve(config()->string('ts-publish.resource_collector_class'));

        /** @var Collection<int, ResourceGenerator> $resourceGenerators */
        $resourceGenerators = collect();

        foreach ($collector->collect() as $resourceClass) {
            /** @var ResourceGenerator $generator */
            $generator = resolve(
                config()->string('ts-publish.resource_generator_class'),
                ['findable' => $resourceClass],
            );

            $resourceGenerators->push($generator);
        }

        $this->resourceGenerators = $resourceGenerators;

        if (config()->boolean('ts-publish.modular_publishing')) {
            $this->resourceModularBarrels = $this->barrelWriter->writeModular($this->resourceGenerators);
            $this->resourceBarrelContent = implode("\n\n", $this->resourceModularBarrels);
        } else {
            $this->resourceBarrelContent = $this->barrelWriter->write(
                $this->resourceGenerators,
                'index',
                config()->string('ts-publish.resources_namespace', 'resources')
            );
        }
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
        $collector = resolve(config()->string('ts-publish.route_collector_class'));

        /** @var Collection<int, RouteGenerator> $routeGenerators */
        $routeGenerators = collect();

        foreach ($collector->collect() as $controllerClass) {
            /** @var RouteGenerator $generator */
            $generator = resolve(
                config()->string('ts-publish.route_generator_class'),
                ['findable' => $controllerClass],
            );

            $routeGenerators->push($generator);
        }

        $this->routeGenerators = $routeGenerators;

        /** @var RouteWriter $routeWriter */
        $routeWriter = resolve(config()->string('ts-publish.route_writer_class'));

        $this->routeModularBarrels = $routeWriter->writeRouteBarrels($this->routeGenerators);
        $this->routeBarrelContent = implode("\n\n", $this->routeModularBarrels);
    }

    protected function generateGlobals(): void
    {
        /** @var GlobalsWriter $globalsWriter */
        $globalsWriter = resolve(config()->string('ts-publish.globals_writer_class'));
        $this->globalsWriter = $globalsWriter;

        $this->globalsContent = $globalsWriter->write($this);
    }

    protected function generateJson(): void
    {
        /** @var JsonWriter $jsonWriter */
        $jsonWriter = resolve(config()->string('ts-publish.json_writer_class'));

        $this->jsonContent = $jsonWriter->write($this);
    }

    protected function generateWatcherJson(): void
    {
        /** @var WatcherJsonWriter $jsonWriter */
        $jsonWriter = resolve(config()->string('ts-publish.watcher_json_writer_class'));

        $this->watcherJsonContent = $jsonWriter->write();
    }
}
