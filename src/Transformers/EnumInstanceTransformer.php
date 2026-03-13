<?php

namespace AbeTwoThree\LaravelTsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Dtos\TsEnumDto;
use BackedEnum;
use UnitEnum;

/**
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
        return [
            'name' => $this->enum->name,
            'value' => $this->value,
            'backed' => $this->data->backed,
            ...$this->resolvedMethods(),
            ...$this->resolvedStaticMethods(),
        ];
    }

    /** @return MethodList */
    protected function resolvedMethods(): array
    {
        $methods = [];

        foreach ($this->data->methods as $methodName => $methodData) {
            $methods[$methodName] = $methodData['returns'][$this->enum->name] ?? '';
        }

        return $methods;
    }

    /** @return StaticMethodList */
    protected function resolvedStaticMethods(): array
    {
        $static = [];

        foreach ($this->data->staticMethods as $methodName => $methodData) {
            $static[$methodName] = $methodData['return'] ?? '';
        }

        return $static;
    }
}
