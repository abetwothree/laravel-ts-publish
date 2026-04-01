<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers;

use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
use AbeTwoThree\LaravelTsPublish\Runners\Runner;
use AbeTwoThree\LaravelTsPublish\Transformers\EnumTransformer;
use Illuminate\Filesystem\Filesystem;

/**
 * @phpstan-import-type CasesList from EnumTransformer
 * @phpstan-import-type CaseKindsList from EnumTransformer
 * @phpstan-import-type CaseTypesList from EnumTransformer
 * @phpstan-import-type MethodsList from EnumTransformer
 * @phpstan-import-type StaticMethodsList from EnumTransformer
 */
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
            $jsonDir = config('ts-publish.json_output_directory');
            $outputPath = is_string($jsonDir) ? $jsonDir : config()->string('ts-publish.output_directory');
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
            'resources' => $this->createJsonForResources($runner),
        ];

        return (string) json_encode($data, JSON_PRETTY_PRINT);
    }

    /** @return array<string, list<array{name: string, type: string}>> */
    protected function createJsonForModels(Runner $runner): array
    {
        $transformers = $runner->modelGenerators->map(fn (ModelGenerator $g) => $g->transformer);
        $data = [];

        foreach ($transformers as $transformer) {
            $data[$transformer->modelName] = [];

            $columns = array_map(fn ($entry, $col) => [
                'name' => $col,
                'type' => $entry['type'],
            ], $transformer->columns, array_keys($transformer->columns));

            $mutators = array_map(fn ($entry, $col) => [
                'name' => $col,
                'type' => $entry['type'],
            ], $transformer->mutators, array_keys($transformer->mutators));

            $appends = array_map(fn ($entry, $col) => [
                'name' => $col,
                'type' => $entry['type'],
            ], $transformer->appends, array_keys($transformer->appends));

            $relations = array_map(fn ($entry, $col) => [
                'name' => $col,
                'type' => $entry['type'],
            ], $transformer->relations, array_keys($transformer->relations));

            $relationCounts = array_map(fn ($entry, $col) => [
                'name' => $col.'_count',
                'type' => 'number',
            ], $transformer->relations, array_keys($transformer->relations));

            $relationExists = array_map(fn ($entry, $col) => [
                'name' => $col.'_exists',
                'type' => 'boolean',
            ], $transformer->relations, array_keys($transformer->relations));

            $data[$transformer->modelName] = [
                ...$columns,
                ...$appends,
                ...$mutators,
                ...$relations,
                ...$relationCounts,
                ...$relationExists,
            ];
        }

        return $data;
    }

    /**
     * @return array<string, array{
     *  cases: CasesList,
     *  caseKinds: CaseKindsList,
     *  caseTypes: CaseTypesList,
     *  methods: MethodsList,
     *  staticMethods: StaticMethodsList
     * }>
     */
    protected function createJsonForEnums(Runner $runner): array
    {
        /** @var list<EnumTransformer> $transformers */
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

    /** @return array<string, list<array{name: string, type: string, optional: bool}>> */
    protected function createJsonForResources(Runner $runner): array
    {
        $transformers = $runner->resourceGenerators->map(fn (ResourceGenerator $g) => $g->transformer);
        $data = [];

        foreach ($transformers as $transformer) {
            $data[$transformer->resourceName] = array_map(
                fn (array $prop, string $name) => [
                    'name' => $name,
                    'type' => $prop['type'],
                    'optional' => $prop['optional'],
                ],
                $transformer->properties,
                array_keys($transformer->properties),
            );
        }

        return $data;
    }
}
