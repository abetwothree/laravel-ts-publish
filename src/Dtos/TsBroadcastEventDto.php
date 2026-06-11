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
 * @phpstan-type PropertyInfo = array{type: string, optional: bool}
 * @phpstan-type PropertiesList = array<string, PropertyInfo>
 * @phpstan-type BroadcastEventData = array{
 *     eventName: string,
 *     broadcastName: string,
 *     fqcn: string,
 *     description: string,
 *     filename: string,
 *     namespacePath: string,
 *     properties: PropertiesList,
 *     typeImports: TypesImportMap,
 *     tsExtends: list<string>,
 * }
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class TsBroadcastEventDto implements Arrayable, Datable, Jsonable, JsonSerializable
{
    /**
     * @param  PropertiesList  $properties  Map of property name → ['type' => 'number', 'optional' => false]
     * @param  TypesImportMap  $typeImports  Map of import path → list of type names to import
     * @param  list<string>  $tsExtends  TypeScript extends clauses
     */
    public function __construct(
        public string $eventName,
        public string $broadcastName,
        public string $fqcn,
        public string $description,
        public string $filename,
        public string $namespacePath,
        public array $properties,
        public array $typeImports,
        public array $tsExtends = [],
    ) {}

    /** @return BroadcastEventData */
    public function toArray(): array
    {
        return [
            'eventName' => $this->eventName,
            'broadcastName' => $this->broadcastName,
            'fqcn' => $this->fqcn,
            'description' => $this->description,
            'filename' => $this->filename,
            'namespacePath' => $this->namespacePath,
            'properties' => $this->properties,
            'typeImports' => $this->typeImports,
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
