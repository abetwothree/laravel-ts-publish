<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish;

use AbeTwoThree\LaravelTsPublish\Collectors\EnumsCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\ModelsCollector;
use AbeTwoThree\LaravelTsPublish\Generators\CoreGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Writers\BarrelWriter;

class Runner
{
    protected BarrelWriter $barrelWriter;

    /** @var array<int, ModelGenerator> */
    public protected(set) array $modelGenerators = [];

    /** @var array<int, EnumGenerator> */
    public protected(set) array $enumGenerators = [];

    public protected(set) string $enumBarrelContent;

    public protected(set) string $modelBarrelContent;

    public function run(): void
    {
        /** @var BarrelWriter $barrelWriter */
        $barrelWriter = resolve(config()->string('ts-publish.barrel_writer_class'));
        $this->barrelWriter = $barrelWriter;

        $this->generateEnums();
        $this->generateModels();
    }

    protected function generateEnums(): void
    {
        /** @var EnumsCollector $collector */
        $collector = resolve(config()->string('ts-publish.enum_collector_class'));

        foreach ($collector->collect() as $enumClass) {
            /** @var EnumGenerator $generator */
            $generator = resolve(
                config()->string('ts-publish.enum_generator_class'),
                ['findable' => $enumClass],
            );
            $this->enumGenerators[] = $generator;
        }

        /** @var \Illuminate\Support\Collection<int, CoreGenerator<mixed>> $enumCollection */
        $enumCollection = collect($this->enumGenerators);

        $this->enumBarrelContent = $this->barrelWriter->write(
            $enumCollection,
            'index',
            'enums'
        );
    }

    protected function generateModels(): void
    {
        /** @var ModelsCollector $collector */
        $collector = resolve(config()->string('ts-publish.model_collector_class'));

        foreach ($collector->collect() as $modelClass) {
            /** @var ModelGenerator $generator */
            $generator = resolve(
                config()->string('ts-publish.model_generator_class'),
                ['findable' => $modelClass],
            );
            $this->modelGenerators[] = $generator;
        }

        /** @var \Illuminate\Support\Collection<int, CoreGenerator<mixed>> $modelCollection */
        $modelCollection = collect($this->modelGenerators);

        $this->modelBarrelContent = $this->barrelWriter->write(
            $modelCollection,
            'index',
            'models'
        );
    }
}
