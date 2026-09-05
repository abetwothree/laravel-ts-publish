<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Dtos;

use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

/**
 * @phpstan-import-type TypesImportMap from Datable
 *
 * @phpstan-type MetadataProperties = array<string, mixed>
 * @phpstan-type MetadataPropertyTypes = array<string, string>
 * @phpstan-type ModelMetadataData = array{
 *     modelName: string,
 *     filename: string,
 *     properties: MetadataProperties,
 *     propertyTypes: MetadataPropertyTypes,
 *     typeImports: TypesImportMap,
 * }
 *
 * @implements Arrayable<string, string|MetadataProperties|MetadataPropertyTypes|TypesImportMap>
 */
final readonly class TsModelMetadataDto implements Arrayable, Datable, Jsonable, JsonSerializable
{
    /**
     * Create model metadata transfer data.
     *
     * @param  MetadataProperties  $properties
     * @param  MetadataPropertyTypes  $propertyTypes
     * @param  TypesImportMap  $typeImports
     */
    public function __construct(
        public string $modelName,
        public string $filename,
        public array $properties,
        public array $propertyTypes,
        public array $typeImports,
    ) {}

    /**
     * Convert the metadata to an array.
     *
     * @return ModelMetadataData
     */
    public function toArray(): array
    {
        return [
            'modelName' => $this->modelName,
            'filename' => $this->filename,
            'properties' => $this->properties,
            'propertyTypes' => $this->propertyTypes,
            'typeImports' => $this->typeImports,
        ];
    }

    /**
     * Convert the metadata to JSON.
     */
    public function toJson($options = 0): string
    {
        return (string) json_encode($this->toArray(), $options);
    }

    /**
     * Prepare the metadata for JSON serialization.
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
