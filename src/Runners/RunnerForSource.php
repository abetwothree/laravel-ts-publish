<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Runners;

use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use Illuminate\Database\Eloquent\Model;
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
    }

    public function run(): void
    {
        $fqcn = $this->resolveSourceToFqcn();

        if (! class_exists($fqcn)) {
            throw new InvalidArgumentException("Class does not exist: {$fqcn}");
        }

        $reflection = new ReflectionClass($fqcn);

        if ($reflection->isEnum()) {
            $this->generateEnum($fqcn);
        } elseif ($reflection->isSubclassOf(Model::class) && ! $reflection->isAbstract()) {
            $this->generateModel($fqcn);
        } else {
            throw new InvalidArgumentException("Class is not a publishable enum or model: {$fqcn}");
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
}
