<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Dtos;

use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

/**
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
 *     fields: list<FormRequestFieldData>,
 *     isDynamic: bool,
 * }
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class TsFormRequestDto implements Arrayable, Datable, Jsonable, JsonSerializable
{
    /**
     * @param  list<FormRequestFieldData>  $fields
     */
    public function __construct(
        public string $fqcn,
        public string $filename,
        public string $namespacePath,
        public string $typeName,
        public array $fields,
        public bool $isDynamic = false,
    ) {}

    /** @return FormRequestData */
    public function toArray(): array
    {
        return [
            'fqcn' => $this->fqcn,
            'filename' => $this->filename,
            'namespacePath' => $this->namespacePath,
            'typeName' => $this->typeName,
            'fields' => $this->fields,
            'isDynamic' => $this->isDynamic,
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
