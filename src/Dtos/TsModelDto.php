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
 * @phpstan-type ColumnsList = array<string, array{type: string, description: string, optional: bool}>
 * @phpstan-type MutatorsList = array<string, array{type: string, description: string, optional: bool}>
 * @phpstan-type RelationsList = array<string, array{type: string, description: string}>
 * @phpstan-type EnumPropertyInfo = array{constName: string, nullable: bool, isCollection: bool}
 * @phpstan-type EnumPropertiesList = array<string, EnumPropertyInfo>
 * @phpstan-type AppendsList = array<string, array{type: string, description: string, optional: bool}>
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
 *    shadowedKeys: list<string>|null,
 *    relationCountKeys: list<string>|null,
 *    relationExistsKeys: list<string>|null,
 *    enumColumns: EnumPropertiesList,
 *    enumMutators: EnumPropertiesList,
 *    enumAppends: EnumPropertiesList,
 *    tsExtends: list<string>,
 *    combinedColumns: ColumnsList|null,
 *    combinedMutators: MutatorsList|null,
 *    combinedAppends: AppendsList|null,
 *    combinedEnums: EnumPropertiesList|null,
 *    combinedTypeImports: TypesImportMap|null,
 *    combinedValueImports: ValuesImportMap|null,
 * }
 *
 * A combined interface declares the attributes beside the relations, as the `model-full` template's model interface and
 * the globals file's do. A key a relation shares is the relation's there, so each `combined*` member leaves out the
 * attribute under it, and the imports only that attribute used. A DTO built without these members renders as a model
 * that shares no key: no shadowed key, each `combined*` list the full one, and a count and exists key per relation.
 *
 * @implements Arrayable<string, string|ColumnsList|RelationsList|MutatorsList|AppendsList|TypesImportMap|ValuesImportMap|EnumPropertiesList|list<string>|null>
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
     * @param  list<string>|null  $shadowedKeys  attribute keys a relation also publishes
     * @param  list<string>|null  $relationCountKeys  the `_count` keys to publish
     * @param  list<string>|null  $relationExistsKeys  the `_exists` keys to publish
     * @param  ColumnsList|null  $combinedColumns  the columns a combined interface declares
     * @param  MutatorsList|null  $combinedMutators  the mutators a combined interface declares
     * @param  AppendsList|null  $combinedAppends  the appends a combined interface declares
     * @param  EnumPropertiesList|null  $combinedEnums  the enum keys a combined interface's `Resource` re-declares
     * @param  TypesImportMap|null  $combinedTypeImports  the type imports a combined interface uses
     * @param  ValuesImportMap|null  $combinedValueImports  the const imports a combined interface's `Resource` uses
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
        public ?array $shadowedKeys = null,
        public ?array $relationCountKeys = null,
        public ?array $relationExistsKeys = null,
        public ?array $combinedColumns = null,
        public ?array $combinedMutators = null,
        public ?array $combinedAppends = null,
        public ?array $combinedEnums = null,
        public ?array $combinedTypeImports = null,
        public ?array $combinedValueImports = null,
    ) {}

    /**
     * An interface name, wrapped in `Omit<>` for each of its keys a relation also publishes, so that a combined
     * interface can extend it beside the relations interface.
     *
     * @param  list<int|string>  $keys  the keys the interface declares
     */
    public function withoutShadowedKeys(string $interface, array $keys): string
    {
        $shadowed = array_values(array_intersect($this->shadowedKeys ?? [], array_map(strval(...), $keys)));

        if ($shadowed === []) {
            return $interface;
        }

        return 'Omit<'.$interface.', '.implode(' | ', array_map(fn (string $key): string => "'".$key."'", $shadowed)).'>';
    }

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
            'shadowedKeys' => $this->shadowedKeys,
            'relationCountKeys' => $this->relationCountKeys,
            'relationExistsKeys' => $this->relationExistsKeys,
            'typeImports' => $this->typeImports,
            'valueImports' => $this->valueImports,
            'enumColumns' => $this->enumColumns,
            'enumMutators' => $this->enumMutators,
            'enumAppends' => $this->enumAppends,
            'tsExtends' => $this->tsExtends,
            'combinedColumns' => $this->combinedColumns,
            'combinedMutators' => $this->combinedMutators,
            'combinedAppends' => $this->combinedAppends,
            'combinedEnums' => $this->combinedEnums,
            'combinedTypeImports' => $this->combinedTypeImports,
            'combinedValueImports' => $this->combinedValueImports,
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
