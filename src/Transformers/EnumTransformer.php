<?php

namespace AbeTwoThree\LaravelTsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCase;
use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumMethod;
use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumStaticMethod;
use BackedEnum;
use Illuminate\Support\Str;
use Override;
use ReflectionEnum;
use Throwable;
use UnitEnum;

/**
 * @extends CoreTransformer<UnitEnum|BackedEnum>
 */
class EnumTransformer extends CoreTransformer
{
    public protected(set) ReflectionEnum $reflectionEnum;

    /** @var array<string, array{value: string|int, description: string}> */
    public protected(set) array $cases = [];

    /** @var array<string, array{name?: string, value?: string|int, description?: string}> */
    public protected(set) array $tsTypeOverrides = []; // column name → raw TS type string from #[TsCase]

    /** @var array<string, array{name?: string, description?: string, returns: array<string, mixed>}> */
    public protected(set) array $methods = []; // method name → ['name' => string, 'description' => string]

    /** @var array<string, array{name?: string, description?: string, return: mixed}> */
    public protected(set) array $staticMethods = [];

    #[Override]
    public function transform(): self
    {
        $this->initInstance()
            ->parseTsTypeOverrides()
            ->transformCases()
            ->transformMethods()
            ->transformStaticMethods();

        return $this;
    }

    protected function initInstance(): self
    {
        $this->reflectionEnum = new ReflectionEnum($this->findable);

        return $this;
    }

    protected function parseTsTypeOverrides(): self
    {
        foreach ($this->reflectionEnum->getCases() as $case) {
            $caseName = $case->getName();
            $constant = $this->reflectionEnum->getReflectionConstant($caseName);

            if ($constant->isPublic() && $constant->isEnumCase()) {
                $tsCaseAttribute = $constant->getAttributes(TsCase::class)[0] ?? null;

                if ($tsCaseAttribute) {
                    /** @var TsCase $tsCaseInstance */
                    $tsCaseInstance = $tsCaseAttribute->newInstance();

                    $this->tsTypeOverrides[$caseName] = [
                        'name' => $tsCaseInstance->name ?: null,
                        'value' => $tsCaseInstance->value ?: null,
                        'description' => $tsCaseInstance->description ?: null,
                    ];
                }
            }
        }

        return $this;
    }

    protected function transformCases(): self
    {
        foreach ($this->reflectionEnum->getCases() as $case) {
            $caseName = $case->getName();
            $caseValue = $case->getValue();

            $override = $this->tsTypeOverrides[$caseName] ?? [];

            $this->cases[] = [
                'name' => $override['name'] ?? $caseName,
                'value' => $override['value'] ?? ($this->reflectionEnum->isBacked() ? $caseValue->value : $caseName),
                'description' => $override['description'] ?? '',
            ];
        }

        return $this;
    }

    protected function transformMethods(): self
    {
        foreach ($this->reflectionEnum->getMethods() as $method) {
            $methodName = $method->getName();

            $tsEnumMethodAttribute = $method->getAttributes(TsEnumMethod::class)[0] ?? null;

            if ($tsEnumMethodAttribute) {
                /** @var TsEnumMethod $tsEnumMethodInstance */
                $tsEnumMethodInstance = $tsEnumMethodAttribute->newInstance();

                // get the returns, we need to call the method with each case instance and collect the return values to know which TS types to import
                $returns = [];
                foreach ($this->reflectionEnum->getCases() as $case) {
                    try {
                        $caseInstance = $this->reflectionEnum->isBacked()
                            ? $this->findable::from($case->getValue()->value)
                            : $case->getValue();
                        $returns[$case->getName()] = $method->invoke($caseInstance);
                    } catch (Throwable) {
                        $returns[$case->getName()] = null;
                    }
                }

                $this->methods[$methodName] = [
                    'name' => $tsEnumMethodInstance->name ?: $methodName,
                    'description' => $tsEnumMethodInstance->description ?: null,
                    'returns' => $returns,
                ];
            }
        }

        return $this;
    }

    protected function transformStaticMethods(): self
    {
        foreach ($this->reflectionEnum->getMethods() as $method) {
            $methodName = $method->getName();

            $tsEnumMethodAttribute = $method->getAttributes(TsEnumStaticMethod::class)[0] ?? null;

            if ($tsEnumMethodAttribute) {
                /** @var TsEnumStaticMethod $tsEnumMethodInstance */
                $tsEnumMethodInstance = $tsEnumMethodAttribute->newInstance();

                // For methods, we just call it once and get the return value. It should be a primitive or an array of primitives that can be transformed to JavaScript for functional use.
                $return = null;
                try {
                    $case = $this->reflectionEnum->getCases()[0] ?? null;
                    if ($case) {
                        $caseInstance = $this->reflectionEnum->isBacked()
                            ? $this->findable::from($case->getValue()->value)
                            : $case->getValue();
                        $return = $method->invoke($caseInstance);
                    }
                } catch (Throwable $e) {
                    // If the method requires parameters or something else goes wrong, we just ignore the return value and hope for the best
                }

                $this->staticMethods[$methodName] = [
                    'name' => $tsEnumMethodInstance->name ?: $methodName,
                    'description' => $tsEnumMethodInstance->description ?: null,
                    'return' => $return,
                ];
            }
        }

        return $this;
    }

    /**
     * @return array{cases: array, enumName: string, methods: array, staticMethods: array, backed: bool}
     */
    #[Override]
    public function data(): array
    {
        return [
            'enumName' => $this->reflectionEnum->getShortName(),
            'cases' => $this->cases,
            'methods' => $this->methods,
            'staticMethods' => $this->staticMethods,
            'backed' => $this->reflectionEnum->isBacked(),
        ];
    }

    #[Override]
    public function filename(): string
    {
        return Str::kebab($this->reflectionEnum->getShortName());
    }
}
