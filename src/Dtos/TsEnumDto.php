<?php

namespace AbeTwoThree\LaravelTsPublish\Dtos;

use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

/**
 * @phpstan-type CaseData = array{name: string, value: string|int, description: string}
 * @phpstan-type CasesList = list<CaseData>
 * @phpstan-type MethodsList = array<string, array{name: string, description: string, returns: array<string, mixed>}>
 * @phpstan-type StaticMethodsList = array<string, array{name: string, description: string, return: mixed}>
 * @phpstan-type CaseKindsList = list<string>
 * @phpstan-type CaseTypesList = list<string|int>
 * @phpstan-type EnumData = array{
 *     enumName: string,
 *     description: string,
 *     filePath: string,
 *     filename: string,
 *     cases: CasesList,
 *     methods: MethodsList,
 *     staticMethods: StaticMethodsList,
 *     caseKinds: CaseKindsList,
 *     caseTypes: CaseTypesList,
 *     backed: bool,
 * }
 *
 * @implements Arrayable<string, string|CasesList|MethodsList|StaticMethodsList|CaseKindsList|CaseTypesList|bool>
 */
class TsEnumDto implements Arrayable, Datable, Jsonable, JsonSerializable
{
    /**
     * @param  CasesList  $cases
     * @param  MethodsList  $methods
     * @param  StaticMethodsList  $staticMethods
     * @param  CaseKindsList  $caseKinds
     * @param  CaseTypesList  $caseTypes
     */
    public function __construct(
        public string $enumName,
        public string $description,
        public string $filePath,
        public string $filename,
        public array $cases,
        public array $methods,
        public array $staticMethods,
        public array $caseKinds,
        public array $caseTypes,
        public bool $backed,
    ) {}

    /** @return EnumData */
    public function toArray(): array
    {
        return [
            'enumName' => $this->enumName,
            'description' => $this->description,
            'filePath' => $this->filePath,
            'filename' => $this->filename,
            'cases' => $this->cases,
            'methods' => $this->methods,
            'staticMethods' => $this->staticMethods,
            'caseKinds' => $this->caseKinds,
            'caseTypes' => $this->caseTypes,
            'backed' => $this->backed,
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
