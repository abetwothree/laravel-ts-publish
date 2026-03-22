<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Runners;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use ReflectionClass;

class RunnerForSource extends BaseRunner
{
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
    }

    public function run(): void
    {
        $fqcn = $this->resolveSourceToFqcn();

        if (! class_exists($fqcn)) {
            throw new InvalidArgumentException("Class does not exist: {$fqcn}");
        }

        $reflection = new ReflectionClass($fqcn);

        if ($reflection->isEnum()) {
            if (! $this->shouldPublishEnums) {
                throw new InvalidArgumentException("Enum publishing is disabled: {$fqcn}");
            }

            $this->generateEnum($fqcn);
        } elseif ($reflection->isSubclassOf(Model::class) && ! $reflection->isAbstract()) {
            if (! $this->shouldPublishModels) {
                throw new InvalidArgumentException("Model publishing is disabled: {$fqcn}");
            }

            $this->generateModel($fqcn);
        } elseif ($this->validResource($reflection)) {
            if (! $this->shouldPublishResources) {
                throw new InvalidArgumentException("Resource publishing is disabled: {$fqcn}");
            }

            $this->generateResource($fqcn);
        } else {
            throw new InvalidArgumentException("Class is not a publishable enum, model, or resource: {$fqcn}");
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

    protected function validResource(ReflectionClass $reflection): bool
    {
        return $reflection->isSubclassOf(JsonResource::class)
            && ! $reflection->isAbstract()
            && ! $reflection->isSubclassOf(ResourceCollection::class)
            && $reflection->getName() !== EnumResource::class;
    }
}
