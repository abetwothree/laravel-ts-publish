<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers;

use AbeTwoThree\LaravelTsPublish\Dtos\TsBroadcastEventDto;
use AbeTwoThree\LaravelTsPublish\Dtos\TsFormRequestDto;
use AbeTwoThree\LaravelTsPublish\Generators\BroadcastEventGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\FormRequestGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
use AbeTwoThree\LaravelTsPublish\Runners\Runner;
use AbeTwoThree\LaravelTsPublish\Transformers\EnumTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\Concerns\EnsuresDirectoryExists;
use AbeTwoThree\LaravelTsPublish\Writers\Concerns\WritesGeneratedFiles;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;

/**
 * @phpstan-import-type CasesList from EnumTransformer
 * @phpstan-import-type CaseKindsList from EnumTransformer
 * @phpstan-import-type CaseTypesList from EnumTransformer
 * @phpstan-import-type MethodsList from EnumTransformer
 * @phpstan-import-type StaticMethodsList from EnumTransformer
 * @phpstan-import-type FormRequestFieldData from TsFormRequestDto
 * @phpstan-import-type PropertyInfo from TsBroadcastEventDto
 */
class JsonWriter
{
    use EnsuresDirectoryExists;
    use WritesGeneratedFiles;

    public function __construct(
        protected Filesystem $filesystem,
    ) {}

    public function write(Runner $runner): string
    {
        if (! Config::boolean('ts-publish.json.enabled')) {
            return '';
        }

        $content = $this->createJsonContent($runner);

        if (Config::boolean('ts-publish.output_to_files')) {
            $jsonDir = Config::string('ts-publish.json.output_directory');
            $outputPath = ! empty($jsonDir) ? $jsonDir : Config::string('ts-publish.output_directory');
            $filename = Config::string('ts-publish.json.filename');

            $this->ensureDirectoryExists($outputPath);
            $this->putIfChanged("$outputPath/$filename", $content);
        }

        return $content;
    }

    protected function createJsonContent(Runner $runner): string
    {
        $data = [
            'broadcastEvents' => $this->createJsonForBroadcastEvents($runner),
            'enums' => $this->createJsonForEnums($runner),
            'formRequests' => $this->createJsonForFormRequests($runner),
            'models' => $this->createJsonForModels($runner),
            'resources' => $this->createJsonForResources($runner),
        ];

        return (string) json_encode($data, JSON_PRETTY_PRINT);
    }

    /** @return array<class-string, array{name: string, properties: list<array{name: string, type: string}>}> */
    protected function createJsonForModels(Runner $runner): array
    {
        $transformers = $runner->modelGenerators->map(fn (ModelGenerator $g) => $g->transformer);
        $data = [];

        foreach ($transformers as $transformer) {
            // A loaded relation overwrites an attribute of the same name, so a model lists its properties as one
            // combined interface declares them.
            $ownColumns = $transformer->combinedColumns();
            $ownMutators = $transformer->combinedMutators();
            $ownAppends = $transformer->combinedAppends();

            $columns = array_map(fn ($entry, $col) => [
                'name' => $col,
                'type' => $entry['type'],
            ], $ownColumns, array_keys($ownColumns));

            $mutators = array_map(fn ($entry, $col) => [
                'name' => $col,
                'type' => $entry['type'],
            ], $ownMutators, array_keys($ownMutators));

            $appends = array_map(fn ($entry, $col) => [
                'name' => $col,
                'type' => $entry['type'],
            ], $ownAppends, array_keys($ownAppends));

            $relations = array_map(fn ($entry, $col) => [
                'name' => $col,
                'type' => $entry['type'],
            ], $transformer->relations, array_keys($transformer->relations));

            $relationCounts = array_map(fn (string $name) => [
                'name' => $name,
                'type' => 'number',
            ], $transformer->relationCountKeys());

            $relationExists = array_map(fn (string $name) => [
                'name' => $name,
                'type' => 'boolean',
            ], $transformer->relationExistsKeys());

            $data[$transformer->fqcn()] = [
                'name' => $transformer->modelName,
                'properties' => [
                    ...$columns,
                    ...$appends,
                    ...$mutators,
                    ...$relations,
                    ...$relationCounts,
                    ...$relationExists,
                ],
            ];
        }

        return $data;
    }

    /**
     * @return array<class-string, array{
     *  name: string,
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
            $data[$transformer->fqcn()] = [
                'name' => $transformer->enumName,
                'cases' => $transformer->cases,
                'caseKinds' => $transformer->caseKinds,
                'caseTypes' => $transformer->caseTypes,
                'methods' => $transformer->methods,
                'staticMethods' => $transformer->staticMethods,
            ];
        }

        return $data;
    }

    /**
     * @return array<class-string, array{name: string, typeAlias: string}|array{
     *  name: string,
     *  properties: list<array{name: string, type: string, optional: bool}>
     * }>
     */
    protected function createJsonForResources(Runner $runner): array
    {
        $transformers = $runner->resourceGenerators->map(fn (ResourceGenerator $g) => $g->transformer);
        $data = [];

        foreach ($transformers as $transformer) {
            if ($transformer->typeAlias !== null) {
                $data[$transformer->fqcn()] = ['name' => $transformer->resourceName, 'typeAlias' => $transformer->typeAlias];
            } else {
                $data[$transformer->fqcn()] = [
                    'name' => $transformer->resourceName,
                    'properties' => array_map(
                        fn (array $prop, string $name) => [
                            'name' => $name,
                            'type' => $prop['type'],
                            'optional' => $prop['optional'],
                        ],
                        $transformer->properties,
                        array_keys($transformer->properties),
                    ),
                ];
            }
        }

        return $data;
    }

    /**
     * @return array<class-string, array{name: string, isDynamic: bool, fields: list<FormRequestFieldData>}>
     */
    protected function createJsonForFormRequests(Runner $runner): array
    {
        $data = [];

        foreach ($runner->formRequestGenerators as $generator) {
            /** @var FormRequestGenerator $generator */
            $transformer = $generator->transformer;
            $data[$transformer->fqcn()] = [
                'name' => $transformer->typeName,
                'isDynamic' => $transformer->isDynamic,
                'fields' => $transformer->fields,
            ];
        }

        return $data;
    }

    /**
     * @return array<class-string, array{
     *  name: string,
     *  eventName: string,
     *  broadcastName: string,
     *  properties: list<array{name: string, type: string, optional: bool}>
     * }>
     */
    protected function createJsonForBroadcastEvents(Runner $runner): array
    {
        $data = [];

        foreach ($runner->broadcastEventGenerators as $generator) {
            /** @var BroadcastEventGenerator $generator */
            $transformer = $generator->transformer;
            $data[$transformer->fqcn()] = [
                'name' => $transformer->eventName,
                'eventName' => $transformer->eventName,
                'broadcastName' => $transformer->broadcastName,
                'properties' => array_map(
                    fn (array $prop, string $name) => [
                        'name' => $name,
                        'type' => $prop['type'],
                        'optional' => $prop['optional'],
                    ],
                    $transformer->properties,
                    array_keys($transformer->properties),
                ),
            ];
        }

        return $data;
    }
}
