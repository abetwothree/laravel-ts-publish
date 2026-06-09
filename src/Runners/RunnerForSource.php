<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Runners;

use AbeTwoThree\LaravelTsPublish\Collectors\Concerns\ValidatesCollectorFiles;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Generators\BroadcastEventGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\FormRequestGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\RouteGenerator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use ReflectionClass;

class RunnerForSource extends BaseRunner
{
    use ValidatesCollectorFiles;

    public function __construct(
        protected string $source,
    ) {
        /** @var Collection<int, EnumGenerator> $enumGenerators */
        $enumGenerators = collect();
        $this->enumGenerators = $enumGenerators;

        /** @var Collection<int, ModelGenerator> $modelGenerators */
        $modelGenerators = collect();
        $this->modelGenerators = $modelGenerators;

        /** @var Collection<int, ResourceGenerator> $resourceGenerators */
        $resourceGenerators = collect();
        $this->resourceGenerators = $resourceGenerators;

        /** @var Collection<int, RouteGenerator> $routeGenerators */
        $routeGenerators = collect();
        $this->routeGenerators = $routeGenerators;

        /** @var Collection<int, FormRequestGenerator> $formRequestGenerators */
        $formRequestGenerators = collect();
        $this->formRequestGenerators = $formRequestGenerators;

        /** @var Collection<int, BroadcastEventGenerator> $broadcastEventGenerators */
        $broadcastEventGenerators = collect();
        $this->broadcastEventGenerators = $broadcastEventGenerators;
    }

    public function run(): void
    {
        $fqcn = $this->resolveSourceToFqcn();

        if (! class_exists($fqcn)) {
            throw new InvalidArgumentException("Class does not exist: {$fqcn}");
        }

        $reflection = new ReflectionClass($fqcn);

        if ($this->validateEnum($reflection)) {
            if (! $this->shouldPublishEnums) {
                throw new InvalidArgumentException("Enum publishing is disabled: {$fqcn}");
            }

            $this->generateEnum($fqcn);
        } elseif ($this->validateModel($reflection)) {
            if (! $this->shouldPublishModels) {
                throw new InvalidArgumentException("Model publishing is disabled: {$fqcn}");
            }

            $this->generateModel($fqcn);
        } elseif ($this->validateResource($reflection)) {
            if (! $this->shouldPublishResources) {
                throw new InvalidArgumentException("Resource publishing is disabled: {$fqcn}");
            }

            $this->generateResource($fqcn);
        } elseif ($this->validateController($reflection)) {
            if (! $this->shouldPublishRoutes) {
                throw new InvalidArgumentException("Route publishing is disabled: {$fqcn}");
            }

            $this->generateRoute($fqcn);
        } elseif ($this->validateFormRequest($reflection)) {
            if (! $this->shouldPublishFormRequests) {
                throw new InvalidArgumentException("Form request publishing is disabled: {$fqcn}");
            }

            $this->generateFormRequest($fqcn);
        } elseif ($this->validateBroadcastEvent($reflection)) {
            if (! $this->shouldPublishBroadcastEvents) {
                throw new InvalidArgumentException("Broadcast event publishing is disabled: {$fqcn}");
            }

            $this->generateBroadcastEvent($fqcn);
        } else {
            throw new InvalidArgumentException("Class is not a publishable enum, model, resource, controller, form request, or broadcast event: {$fqcn}");
        }
    }

    protected function resolveSourceToFqcn(): string
    {
        if (str_ends_with($this->source, '.php')) {
            $fqcn = LaravelTsPublish::resolveClassFromFile($this->source);

            if ($fqcn === null) {
                throw new InvalidArgumentException("Could not resolve a class from file: {$this->source}");
            }

            return $fqcn;
        }

        return $this->source;
    }

    protected function generateEnum(string $fqcn): void
    {
        /** @var EnumGenerator $generator */
        $generator = resolve(
            Config::string('ts-publish.enums.generator_class', EnumGenerator::class),
            ['findable' => $fqcn],
        );

        $this->enumGenerators = collect([$generator]);
    }

    protected function generateModel(string $fqcn): void
    {
        /** @var ModelGenerator $generator */
        $generator = resolve(
            Config::string('ts-publish.models.generator_class', ModelGenerator::class),
            ['findable' => $fqcn],
        );

        $this->modelGenerators = collect([$generator]);
    }

    protected function generateResource(string $fqcn): void
    {
        /** @var ResourceGenerator $generator */
        $generator = resolve(
            Config::string('ts-publish.resources.generator_class', ResourceGenerator::class),
            ['findable' => $fqcn],
        );

        $this->resourceGenerators = collect([$generator]);
    }

    /**
     * Generate a route from a controller FQCN.
     *
     * Class existence and TsExclude are already validated by run() → validateController(),
     * so no redundant checks are needed here.
     *
     * @param  class-string  $fqcn  The fully qualified class name of the controller.
     */
    protected function generateRoute(string $fqcn): void
    {
        /** @var RouteGenerator $generator */
        $generator = resolve(
            Config::string('ts-publish.routes.generator_class', RouteGenerator::class),
            ['findable' => $fqcn],
        );

        $this->routeGenerators = collect([$generator]);
    }

    /**
     * Generate a FormRequest interface from its FQCN.
     *
     * @param  class-string  $fqcn  The fully qualified class name of the FormRequest.
     */
    protected function generateFormRequest(string $fqcn): void
    {
        /** @var FormRequestGenerator $generator */
        $generator = resolve(
            Config::string('ts-publish.form_requests.generator_class', FormRequestGenerator::class),
            ['findable' => $fqcn],
        );

        $this->formRequestGenerators = collect([$generator]);
    }

    /**
     * Generate a broadcast event interface from its FQCN.
     *
     * @param  class-string  $fqcn  The fully qualified class name of the broadcast event.
     */
    protected function generateBroadcastEvent(string $fqcn): void
    {
        /** @var BroadcastEventGenerator $generator */
        $generator = resolve(
            Config::string('ts-publish.broadcast_events.generator_class', BroadcastEventGenerator::class),
            ['findable' => $fqcn],
        );

        $this->broadcastEventGenerators = collect([$generator]);
    }
}
