<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Dtos;

use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

/**
 * @phpstan-import-type TypesImportMap from Datable
 * @phpstan-import-type ValuesImportMap from Datable
 *
 * @phpstan-type ColumnsList = array<string, array{type: string, description: string}>
 * @phpstan-type MutatorsList = array<string, array{type: string, description: string}>
 * @phpstan-type RelationsList = array<string, array{type: string, description: string}>
 * @phpstan-type EnumPropertyInfo = array{constName: string, nullable: bool}
 * @phpstan-type EnumPropertiesList = array<string, EnumPropertyInfo>
 * @phpstan-type AppendsList = array<string, array{type: string, description: string}>
 * @phpstan-type ModelData = array{
 *    modelName: string,
 *    description: string,
 *    fqcn: string,
 *    filePath: string,
 *    filename: string,
 *    columns: ColumnsList,
 *    typeImports: TypesImportMap,
 *    valueImports: ValuesImportMap,
 *    mutators: MutatorsList,
 *    appends: AppendsList,
 *    relations: RelationsList,
 *    enumColumns: EnumPropertiesList,
 *    enumMutators: EnumPropertiesList,
 *    enumAppends: EnumPropertiesList,
 *    tsExtends: list<string>,
 * }
 *
 * @implements Arrayable<string, string|ColumnsList|RelationsList|MutatorsList|AppendsList|TypesImportMap|ValuesImportMap|EnumPropertiesList|list<string>>
 */
final readonly class TsModelDto implements Arrayable, Datable, Jsonable, JsonSerializable
{
    /**
     * @param  ColumnsList  $columns
     * @param  MutatorsList  $mutators
     * @param  AppendsList  $appends
     * @param  RelationsList  $relations
     * @param  TypesImportMap  $typeImports
     * @param  ValuesImportMap  $valueImports
     * @param  EnumPropertiesList  $enumColumns
     * @param  EnumPropertiesList  $enumMutators
     * @param  EnumPropertiesList  $enumAppends
     * @param  list<string>  $tsExtends
     */
    public function __construct(
        public string $modelName,
        public string $description,
        public string $fqcn,
        public string $filePath,
        public string $filename,
        public array $columns,
        public array $mutators,
        public array $appends,
        public array $relations,
        public array $typeImports,
        public array $valueImports = [],
        public array $enumColumns = [],
        public array $enumMutators = [],
        public array $enumAppends = [],
        public array $tsExtends = [],
    ) {}

    /** @return ModelData */
    public function toArray(): array
    {
        return [
            'modelName' => $this->modelName,
            'description' => $this->description,
            'fqcn' => $this->fqcn,
            'filePath' => $this->filePath,
            'filename' => $this->filename,
            'columns' => $this->columns,
            'mutators' => $this->mutators,
            'appends' => $this->appends,
            'relations' => $this->relations,
            'typeImports' => $this->typeImports,
            'valueImports' => $this->valueImports,
            'enumColumns' => $this->enumColumns,
            'enumMutators' => $this->enumMutators,
            'enumAppends' => $this->enumAppends,
            'tsExtends' => $this->tsExtends,
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
