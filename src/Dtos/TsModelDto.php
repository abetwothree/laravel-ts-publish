<?php

namespace AbeTwoThree\LaravelTsPublish\Dtos;

use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

/**
 * @phpstan-type ColumnsList = array<string, array{type: string, description: string}>
 * @phpstan-type ResolvedImportMap = array<string, list<string>>
 * @phpstan-type MutatorsList = array<string, array{type: string, description: string}>
 * @phpstan-type RelationsList = array<string, array{type: string, description: string}>
 * @phpstan-type EnumPropertyInfo = array{constName: string, nullable: bool}
 * @phpstan-type EnumPropertiesList = array<string, EnumPropertyInfo>
 * @phpstan-type ModelData = array{
 *    modelName: string,
 *    description: string,
 *    filePath: string,
 *    filename: string,
 *    columns: ColumnsList,
 *    resolvedImports: ResolvedImportMap,
 *    mutators: MutatorsList,
 *    relations: RelationsList,
 *    enumColumns: EnumPropertiesList,
 *    enumMutators: EnumPropertiesList,
 * }
 *
 * @implements Arrayable<string, string|ColumnsList|RelationsList|MutatorsList|ResolvedImportMap|EnumPropertiesList>
 */
class TsModelDto implements Arrayable, Datable, Jsonable, JsonSerializable
{
    /**
     * @param  ColumnsList  $columns
     * @param  MutatorsList  $mutators
     * @param  RelationsList  $relations
     * @param  ResolvedImportMap  $resolvedImports
     * @param  EnumPropertiesList  $enumColumns
     * @param  EnumPropertiesList  $enumMutators
     */
    public function __construct(
        public string $modelName,
        public string $description,
        public string $filePath,
        public string $filename,
        public array $columns,
        public array $mutators,
        public array $relations,
        public array $resolvedImports,
        public array $enumColumns = [],
        public array $enumMutators = [],
    ) {}

    /** @return ModelData */
    public function toArray(): array
    {
        return [
            'modelName' => $this->modelName,
            'description' => $this->description,
            'filePath' => $this->filePath,
            'filename' => $this->filename,
            'columns' => $this->columns,
            'mutators' => $this->mutators,
            'relations' => $this->relations,
            'resolvedImports' => $this->resolvedImports,
            'enumColumns' => $this->enumColumns,
            'enumMutators' => $this->enumMutators,
        ];
    }

    public function toJson($options = 0): string
    {
        return (string) json_encode($this->toArray(), $options);
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
