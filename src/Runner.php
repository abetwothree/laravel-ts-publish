<?php

namespace AbeTwoThree\LaravelTsPublish;

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
        $this->barrelWriter = resolve(config()->string('ts-publish.barrel_writer_class'));

        $this->generateEnums();
        $this->generateModels();
    }

    protected function generateEnums(): void
    {
        foreach (resolve(config()->string('ts-publish.enum_collector_class'))->collect() as $enumClass) {
            $this->enumGenerators[] = resolve(
                config()->string('ts-publish.enum_generator_class'),
                ['findable' => $enumClass],
            );
        }

        $this->enumBarrelContent = $this->barrelWriter->write(
            collect($this->enumGenerators),
            'index',
            'enums'
        );
    }

    protected function generateModels(): void
    {
        foreach (resolve(config()->string('ts-publish.model_collector_class'))->collect() as $modelClass) {
            $this->modelGenerators[] = resolve(
                config()->string('ts-publish.model_generator_class'),
                ['findable' => $modelClass],
            );
        }

        $this->modelBarrelContent = $this->barrelWriter->write(
            collect($this->modelGenerators),
            'index',
            'models'
        );
    }
}
