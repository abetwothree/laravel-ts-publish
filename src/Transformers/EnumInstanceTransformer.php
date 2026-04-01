<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Dtos\TsEnumDto;
use BackedEnum;
use UnitEnum;

/**
 * @phpstan-import-type CaseData from TsEnumDto
 *
 * @phpstan-type MethodList = array<string, mixed>
 * @phpstan-type StaticMethodList = array<string, mixed>
 * @phpstan-type EnumInstanceData = array<string, mixed>
 */
class EnumInstanceTransformer
{
    protected string|int $value;

    public function __construct(
        protected TsEnumDto $data,
        protected UnitEnum|BackedEnum $enum
    ) {
        $this->value = $enum instanceof BackedEnum ? $enum->value : $enum->name;
    }

    /** @return EnumInstanceData */
    public function data(): array
    {
        $case = $this->matchingCase();

        return [
            'name' => $case['name'],
            'value' => $case['value'],
            'backed' => $this->data->backed,
            ...$this->resolvedMethods(),
            ...$this->resolvedStaticMethods(),
        ];
    }

    /**
     * Find the matching case in the DTO (which has TsCase overrides applied).
     *
     * @return CaseData
     */
    protected function matchingCase(): array
    {
        $index = array_search($this->enum, $this->enum::cases(), true);

        /** @var int $index */
        return $this->data->cases[$index];
    }

    /** @return MethodList */
    protected function resolvedMethods(): array
    {
        return collect($this->data->methods)
            ->mapWithKeys(fn ($methodData) => [
                $methodData['name'] => $methodData['returns'][$this->enum->name] ?? '',
            ])
            ->all();
    }

    /** @return StaticMethodList */
    protected function resolvedStaticMethods(): array
    {
        return collect($this->data->staticMethods)
            ->mapWithKeys(fn ($methodData) => [
                $methodData['name'] => $methodData['return'],
            ])
            ->all();
    }
}
