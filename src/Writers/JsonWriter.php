<?php

namespace AbeTwoThree\LaravelTsPublish\Writers;

use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Runner;
use Illuminate\Filesystem\Filesystem;

class JsonWriter
{
    public function __construct(
        protected Filesystem $filesystem,
    ) {}

    public function write(Runner $runner): string
    {
        if (! config()->boolean('ts-publish.output_json_file')) {
            return '';
        }

        $content = $this->createJsonContent($runner);

        if (config()->boolean('ts-publish.output_to_files')) {
            $outputPath = config()->string('ts-publish.json_output_directory');
            $filename = config()->string('ts-publish.json_filename');

            $this->filesystem->ensureDirectoryExists($outputPath);
            $this->filesystem->put("$outputPath/$filename", $content);
        }

        return $content;
    }

    protected function createJsonContent(Runner $runner): string
    {
        $data = [
            'models' => $this->createJsonForModels($runner),
            'enums' => $this->createJsonForEnums($runner),
        ];

        return json_encode($data, JSON_PRETTY_PRINT);
    }

    protected function createJsonForModels(Runner $runner): array
    {
        $transformers = $runner->modelGenerators->map(fn (ModelGenerator $g) => $g->transformer);
        $data = [];

        foreach ($transformers as $transformer) {
            $data[$transformer->modelName] = [];

            $columns = array_map(fn ($type, $col) => [
                'name' => $col,
                'type' => $type,
            ], $transformer->columns, array_keys($transformer->columns));

            $mutators = array_map(fn ($type, $col) => [
                'name' => $col,
                'type' => $type,
            ], $transformer->mutators, array_keys($transformer->mutators));

            $relations = array_map(fn ($type, $col) => [
                'name' => $col,
                'type' => $type,
            ], $transformer->relations, array_keys($transformer->relations));

            $relationCounts = array_map(fn ($type, $col) => [
                'name' => $col.'_count',
                'type' => 'number',
            ], $transformer->relations, array_keys($transformer->relations));

            $relationExists = array_map(fn ($type, $col) => [
                'name' => $col.'_exists',
                'type' => 'boolean',
            ], $transformer->relations, array_keys($transformer->relations));

            $data[$transformer->modelName] = [
                ...$columns,
                ...$mutators,
                ...$relations,
                ...$relationCounts,
                ...$relationExists,
            ];
        }

        return $data;
    }

    protected function createJsonForEnums(Runner $runner): array
    {
        $transformers = $runner->enumGenerators->map(fn (EnumGenerator $g) => $g->transformer)->toArray();

        $data = [];

        foreach ($transformers as $transformer) {
            $data[$transformer->enumName] = [
                'cases' => $transformer->cases,
                'caseKinds' => $transformer->caseKinds,
                'caseTypes' => $transformer->caseTypes,
                'methods' => $transformer->methods,
                'staticMethods' => $transformer->staticMethods,
            ];
        }

        return $data;
    }
}
