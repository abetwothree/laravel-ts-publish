<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish;

use AbeTwoThree\LaravelTsPublish\Collectors\EnumsCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\ModelsCollector;
use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Writers\BarrelWriter;
use Illuminate\Support\Collection;
use AbeTwoThree\LaravelTsPublish\Writers\GlobalsWriter;
use AbeTwoThree\LaravelTsPublish\Writers\JsonWriter;

class Runner
{
    protected BarrelWriter $barrelWriter;

    protected GlobalsWriter $globalsWriter;

    /** @var Collection<int, EnumGenerator> */
    public protected(set) Collection $enumGenerators;

    /** @var Collection<int, ModelGenerator> */
    public protected(set) Collection $modelGenerators;

    public protected(set) string $enumBarrelContent = '';

    public protected(set) string $modelBarrelContent = '';

    public protected(set) string $globalsContent = '';

    public protected(set) string $jsonContent = '';

    public function run(): void
    {
        /** @var BarrelWriter $barrelWriter */
        $barrelWriter = resolve(config()->string('ts-publish.barrel_writer_class'));
        $this->barrelWriter = $barrelWriter;

        $this->generateEnums();
        $this->generateModels();

        $this->generateGlobals();
        $this->generateJson();

        // TODO: create JS filelist of classes for npm process to watch changes on, and run publish command on change
    }

    protected function generateEnums(): void
    {
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

        $this->enumBarrelContent = $this->barrelWriter->write(
            $this->enumGenerators,
            'index',
            'enums'
        );
    }

    protected function generateModels(): void
    {
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

        $this->modelBarrelContent = $this->barrelWriter->write(
            $this->modelGenerators,
            'index',
            'models'
        );
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
}
