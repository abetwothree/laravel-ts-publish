<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Runners;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExclude;
use AbeTwoThree\LaravelTsPublish\Collectors\Concerns\ValidatesCollectorFiles;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\RouteGenerator;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
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
        } else {
            throw new InvalidArgumentException("Class is not a publishable enum, model, resource, or controller: {$fqcn}");
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
            config()->string('ts-publish.enum_generator_class'),
            ['findable' => $fqcn],
        );

        $this->enumGenerators = collect([$generator]);
    }

    protected function generateModel(string $fqcn): void
    {
        /** @var ModelGenerator $generator */
        $generator = resolve(
            config()->string('ts-publish.model_generator_class'),
            ['findable' => $fqcn],
        );

        $this->modelGenerators = collect([$generator]);
    }

    protected function generateResource(string $fqcn): void
    {
        /** @var ResourceGenerator $generator */
        $generator = resolve(
            config()->string('ts-publish.resource_generator_class'),
            ['findable' => $fqcn],
        );

        $this->resourceGenerators = collect([$generator]);
    }

    /**
     * @param  ReflectionClass<object>  $reflection
     */
    protected function validateController(ReflectionClass $reflection): bool
    {
        // Must be a concrete, non-abstract class
        if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isTrait()) {
            return false;
        }

        // Must have at least one route registered for this controller
        /** @var Router $router */
        $router = app(Router::class);

        $fqcn = $reflection->getName();

        foreach ($router->getRoutes()->getRoutes() as $route) {
            if ($route->getControllerClass() === $fqcn) {
                return true;
            }
        }

        return false;
    }

    protected function generateRoute(string $fqcn): void
    {
        if (! class_exists($fqcn)) {
            throw new InvalidArgumentException("Class does not exist: {$fqcn}");
        }

        // Check for class-level TsExclude
        $reflection = new ReflectionClass($fqcn);

        if ($reflection->getAttributes(TsExclude::class) !== []) {
            throw new InvalidArgumentException("Controller is excluded via #[TsExclude]: {$fqcn}");
        }

        /** @var RouteGenerator $generator */
        $generator = resolve(
            config()->string('ts-publish.route_generator_class'),
            ['findable' => $fqcn],
        );

        $this->routeGenerators = collect([$generator]);
    }
}
