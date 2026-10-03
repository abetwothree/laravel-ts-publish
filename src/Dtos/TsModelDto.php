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
 *    shadowedKeys: list<string>,
 *    relationCountKeys: list<string>,
 *    relationExistsKeys: list<string>,
 *    enumColumns: EnumPropertiesList,
 *    enumMutators: EnumPropertiesList,
 *    enumAppends: EnumPropertiesList,
 *    tsExtends: list<string>,
 *    combinedColumns: ColumnsList,
 *    combinedMutators: MutatorsList,
 *    combinedAppends: AppendsList,
 *    combinedEnums: EnumPropertiesList,
 *    combinedTypeImports: TypesImportMap,
 *    combinedValueImports: ValuesImportMap,
 * }
 *
 * A combined interface declares the attributes beside the relations, as the `model-full` template's model interface and
 * the globals file's do. A key a relation shares is the relation's there, so each `combined*` member leaves out the
 * attribute under it, and the imports only that attribute used. A DTO built without these members reads as a model that
 * shares no key: no shadowed key, each `combined*` list the full one, and a count and exists key per relation.
 *
 * @implements Arrayable<string, string|ColumnsList|RelationsList|MutatorsList|AppendsList|TypesImportMap|ValuesImportMap|EnumPropertiesList|list<string>>
 */
final readonly class TsModelDto implements Arrayable, Datable, Jsonable, JsonSerializable
{
    /** @var list<string> attribute keys a relation also publishes */
    public array $shadowedKeys;

    /** @var list<string> the `_count` keys to publish */
    public array $relationCountKeys;

    /** @var list<string> the `_exists` keys to publish */
    public array $relationExistsKeys;

    /** @var ColumnsList the columns a combined interface declares */
    public array $combinedColumns;

    /** @var MutatorsList the mutators a combined interface declares */
    public array $combinedMutators;

    /** @var AppendsList the appends a combined interface declares */
    public array $combinedAppends;

    /** @var EnumPropertiesList the enum keys a combined interface's `Resource` re-declares */
    public array $combinedEnums;

    /** @var TypesImportMap the type imports a combined interface uses */
    public array $combinedTypeImports;

    /** @var ValuesImportMap the const imports a combined interface's `Resource` uses */
    public array $combinedValueImports;

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
     * @param  list<string>|null  $shadowedKeys  none when null
     * @param  list<string>|null  $relationCountKeys  one per relation when null
     * @param  list<string>|null  $relationExistsKeys  one per relation when null
     * @param  ColumnsList|null  $combinedColumns  every column when null
     * @param  MutatorsList|null  $combinedMutators  every mutator when null
     * @param  AppendsList|null  $combinedAppends  every append when null
     * @param  EnumPropertiesList|null  $combinedEnums  every enum key when null
     * @param  TypesImportMap|null  $combinedTypeImports  every type import when null
     * @param  ValuesImportMap|null  $combinedValueImports  every const import when null
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
        ?array $shadowedKeys = null,
        ?array $relationCountKeys = null,
        ?array $relationExistsKeys = null,
        ?array $combinedColumns = null,
        ?array $combinedMutators = null,
        ?array $combinedAppends = null,
        ?array $combinedEnums = null,
        ?array $combinedTypeImports = null,
        ?array $combinedValueImports = null,
    ) {
        $this->shadowedKeys = $shadowedKeys ?? [];
        $this->relationCountKeys = $relationCountKeys ?? $this->relationKeys('_count');
        $this->relationExistsKeys = $relationExistsKeys ?? $this->relationKeys('_exists');
        $this->combinedColumns = $combinedColumns ?? $columns;
        $this->combinedMutators = $combinedMutators ?? $mutators;
        $this->combinedAppends = $combinedAppends ?? $appends;
        $this->combinedEnums = $combinedEnums ?? $enumColumns + $enumMutators + $enumAppends;
        $this->combinedTypeImports = $combinedTypeImports ?? $typeImports;
        $this->combinedValueImports = $combinedValueImports ?? $valueImports;
    }

    /**
     * An interface name, wrapped in `Omit<>` for each of its keys a relation also publishes, so that a combined
     * interface can extend it beside the relations interface.
     *
     * @param  list<int|string>  $keys  the keys the interface declares
     */
    public function withoutShadowedKeys(string $interface, array $keys): string
    {
        $shadowed = array_values(array_intersect($this->shadowedKeys, array_map(strval(...), $keys)));

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

    /**
     * Each relation's key with the given suffix: a count or exists key per relation.
     *
     * @return list<string>
     */
    private function relationKeys(string $suffix): array
    {
        return array_map(fn (int|string $name): string => $name.$suffix, array_keys($this->relations));
    }
}
