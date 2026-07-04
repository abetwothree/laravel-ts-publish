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
 * @phpstan-type FormRequestFieldData = array{
 *     fieldPath: string,
 *     tsType: string,
 *     isRequired: bool,
 *     isNullable: bool,
 *     isProhibited: bool,
 *     jsDocMetadata: list<string>,
 * }
 * @phpstan-type FormRequestData = array{
 *     fqcn: string,
 *     filename: string,
 *     namespacePath: string,
 *     typeName: string,
 *     description: string,
 *     fields: list<FormRequestFieldData>,
 *     isDynamic: bool,
 *     typeImports: TypesImportMap,
 *     tsExtends: list<string>,
 * }
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class TsFormRequestDto implements Arrayable, Datable, Jsonable, JsonSerializable
{
    /**
     * @param  list<FormRequestFieldData>  $fields
     * @param  TypesImportMap  $typeImports
     * @param  list<string>  $tsExtends  TypeScript extends clauses
     */
    public function __construct(
        public string $fqcn,
        public string $filename,
        public string $namespacePath,
        public string $typeName,
        public string $description,
        public array $fields,
        public bool $isDynamic = false,
        public array $typeImports = [],
        public array $tsExtends = [],
    ) {}

    /** @return FormRequestData */
    public function toArray(): array
    {
        return [
            'fqcn' => $this->fqcn,
            'filename' => $this->filename,
            'namespacePath' => $this->namespacePath,
            'typeName' => $this->typeName,
            'description' => $this->description,
            'fields' => $this->fields,
            'isDynamic' => $this->isDynamic,
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
