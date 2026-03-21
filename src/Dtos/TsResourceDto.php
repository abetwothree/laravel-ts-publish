<?php

namespace AbeTwoThree\LaravelTsPublish\Dtos;

use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

/**
 * @phpstan-type ResourceProperty = array{type: string, optional: bool, description: string}
 * @phpstan-type PropertiesList = array<string, ResourceProperty>
 * @phpstan-type TypesImportMap = array<string, list<string>>
 * @phpstan-type ValuesImportMap = array<string, list<string>>
 * @phpstan-type ResourceData = array{
 *     resourceName: string,
 *     description: string,
 *     filePath: string,
 *     filename: string,
 *     properties: PropertiesList,
 *     typeImports: TypesImportMap,
 *     valueImports: ValuesImportMap,
 *     modelClass: class-string|null,
 * }
 *
 * @implements Arrayable<string, string|PropertiesList|TypesImportMap|ValuesImportMap|null>
 */
class TsResourceDto implements Arrayable, Datable, Jsonable, JsonSerializable
{
    /**
     * @param  PropertiesList  $properties
     * @param  TypesImportMap  $typeImports
     * @param  ValuesImportMap  $valueImports
     * @param  class-string|null  $modelClass
     */
    public function __construct(
        public string $resourceName,
        public string $description,
        public string $filePath,
        public string $filename,
        public array $properties,
        public array $typeImports,
        public array $valueImports = [],
        public ?string $modelClass = null,
    ) {}

    /** @return ResourceData */
    public function toArray(): array
    {
        return [
            'resourceName' => $this->resourceName,
            'description' => $this->description,
            'filePath' => $this->filePath,
            'filename' => $this->filename,
            'properties' => $this->properties,
            'typeImports' => $this->typeImports,
            'valueImports' => $this->valueImports,
            'modelClass' => $this->modelClass,
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
