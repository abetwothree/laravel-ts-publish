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
 * @phpstan-type ResourceProperty = array{type: string, optional: bool, description: string}
 * @phpstan-type PropertiesList = array<string, ResourceProperty>
 * @phpstan-type ResourceData = array{
 *     resourceName: string,
 *     description: string,
 *     fqcn: string,
 *     filePath: string,
 *     filename: string,
 *     properties: PropertiesList,
 *     typeImports: TypesImportMap,
 *     valueImports: ValuesImportMap,
 *     modelClass: class-string|null,
 *     tsExtends: list<string>,
 *     typeAlias: string|null,
 * }
 *
 * @implements Arrayable<string, string|PropertiesList|TypesImportMap|ValuesImportMap|list<string>|null>
 */
final readonly class TsResourceDto implements Arrayable, Datable, Jsonable, JsonSerializable
{
    /**
     * @param  PropertiesList  $properties
     * @param  TypesImportMap  $typeImports
     * @param  ValuesImportMap  $valueImports
     * @param  class-string|null  $modelClass
     * @param  list<string>  $tsExtends
     */
    public function __construct(
        public string $resourceName,
        public string $description,
        public string $fqcn,
        public string $filePath,
        public string $filename,
        public array $properties,
        public array $typeImports,
        public array $valueImports = [],
        public ?string $modelClass = null,
        public array $tsExtends = [],
        public ?string $typeAlias = null,
    ) {}

    /** @return ResourceData */
    public function toArray(): array
    {
        return [
            'resourceName' => $this->resourceName,
            'description' => $this->description,
            'fqcn' => $this->fqcn,
            'filePath' => $this->filePath,
            'filename' => $this->filename,
            'properties' => $this->properties,
            'typeImports' => $this->typeImports,
            'valueImports' => $this->valueImports,
            'modelClass' => $this->modelClass,
            'tsExtends' => $this->tsExtends,
            'typeAlias' => $this->typeAlias,
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
