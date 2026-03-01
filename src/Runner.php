<?php

namespace AbeTwoThree\LaravelTsPublish;

use AbeTwoThree\LaravelTsPublish\Collectors\EnumsCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\ModelsCollector;
use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Writers\BarrelWriter;

class Runner
{
    protected BarrelWriter $barrelWriter;

    /** @var array<int, ModelGenerator> */
    protected array $modelGenerators = [];

    /** @var array<int, EnumGenerator> */
    protected array $enumGenerators = [];

    public function run(): void
    {
        $this->barrelWriter = resolve(BarrelWriter::class);

        $this->generateEnums();
        $this->generateModels();
    }

    protected function generateEnums(): void
    {
        foreach (resolve(EnumsCollector::class)->collect() as $enumClass) {
            $this->enumGenerators[] = new EnumGenerator($enumClass);
        }

        $this->barrelWriter->write(collect($this->enumGenerators), 'index', 'enums');
    }

    protected function generateModels(): void
    {
        foreach (resolve(ModelsCollector::class)->collect() as $modelClass) {
            $this->modelGenerators[] = new ModelGenerator($modelClass);
        }

        $this->barrelWriter->write(collect($this->modelGenerators), 'index', 'models');
    }
}
