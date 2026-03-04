<?php

declare(strict_types=1);

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
 * @phpstan-type CasesList = list<array{name: string, value: string|int, description: string}>
 * @phpstan-type TsTypeOverrides = array<string, array{name?: string, value?: string|int, description?: string}>
 * @phpstan-type MethodsList = array<string, array{name: string, description: string, returns: array<string, mixed>}>
 * @phpstan-type StaticMethodsList = array<string, array{name: string, description: string, return: mixed}>
 * @phpstan-type CaseKindsList = list<string>
 * @phpstan-type CaseTypesList = list<string|int>
 * @phpstan-type EnumData = array{
 *     enumName: string,
 *     filePath: string,
 *     cases: CasesList,
 *     methods: MethodsList,
 *     staticMethods: StaticMethodsList,
 *     caseKinds: CaseKindsList,
 *     caseTypes: CaseTypesList,
 *     backed: bool,
 * }
 *
 * @extends CoreTransformer<UnitEnum|BackedEnum>
 */
class EnumTransformer extends CoreTransformer
{
    public protected(set) string $enumName;

    public protected(set) string $filePath;

    public protected(set) bool $backed;

    /** @var ReflectionEnum<UnitEnum> */
    public protected(set) ReflectionEnum $reflectionEnum;

    /** @var CasesList */
    public protected(set) array $cases = [];

    /** @var TsTypeOverrides */
    public protected(set) array $tsTypeOverrides = [];

    /** @var MethodsList */
    public protected(set) array $methods = [];

    /** @var StaticMethodsList */
    public protected(set) array $staticMethods = [];

    /** @var CaseKindsList */
    public protected(set) array $caseKinds = [];

    /** @var CaseTypesList */
    public protected(set) array $caseTypes = [];

    #[Override]
    public function transform(): self
    {
        $this->initInstance()
            ->parseTsTypeOverrides()
            ->transformCases()
            ->transformMethods()
            ->transformStaticMethods()
            ->transformCaseKindsAndTypes();

        return $this;
    }

    /**
     * @return EnumData
     */
    #[Override]
    public function data(): array
    {
        return [
            'enumName' => $this->enumName,
            'filePath' => $this->filePath,
            'cases' => $this->cases,
            'methods' => $this->methods,
            'staticMethods' => $this->staticMethods,
            'caseKinds' => $this->caseKinds,
            'caseTypes' => $this->caseTypes,
            'backed' => $this->backed,
        ];
    }

    #[Override]
    public function filename(): string
    {
        return Str::kebab($this->enumName);
    }

    protected function initInstance(): self
    {
        $this->reflectionEnum = new ReflectionEnum($this->findable);
        $this->backed = $this->reflectionEnum->isBacked();
        $this->enumName = $this->reflectionEnum->getShortName();
        $this->filePath = $this->resolveRelativePath((string) $this->reflectionEnum->getFileName());

        return $this;
    }

    protected function parseTsTypeOverrides(): self
    {
        foreach ($this->reflectionEnum->getCases() as $case) {
            $caseName = $case->getName();
            $constant = $this->reflectionEnum->getReflectionConstant($caseName);

            if ($constant === false) {
                continue;
            }

            if ($constant->isPublic() && $constant->isEnumCase()) {
                $tsCaseAttribute = $constant->getAttributes(TsCase::class)[0] ?? null;

                if ($tsCaseAttribute) {
                    /** @var TsCase $tsCaseInstance */
                    $tsCaseInstance = $tsCaseAttribute->newInstance();

                    $override = [];

                    if ($tsCaseInstance->name !== '') {
                        $override['name'] = $tsCaseInstance->name;
                    }

                    if ($tsCaseInstance->value !== '') {
                        $override['value'] = $tsCaseInstance->value;
                    }

                    if ($tsCaseInstance->description !== '') {
                        $override['description'] = $tsCaseInstance->description;
                    }

                    $this->tsTypeOverrides[$caseName] = $override;
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

            $value = $caseValue instanceof BackedEnum ? $caseValue->value : $caseName;

            $this->cases[] = [
                'name' => $override['name'] ?? $caseName,
                'value' => $override['value'] ?? $value,
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
                        $caseInstance = $case->getValue();
                        $returns[$case->getName()] = $method->invoke($caseInstance);
                    } catch (Throwable) {
                        $returns[$case->getName()] = null;
                    }
                }

                $this->methods[$methodName] = [
                    'name' => $tsEnumMethodInstance->name ?: $methodName,
                    'description' => $tsEnumMethodInstance->description ?? '',
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
                        $caseInstance = $case->getValue();
                        $return = $method->invoke($caseInstance);
                    }
                } catch (Throwable $e) {
                    // If the method requires parameters or something else goes wrong, we just ignore the return value and hope for the best
                }

                $this->staticMethods[$methodName] = [
                    'name' => $tsEnumMethodInstance->name ?: $methodName,
                    'description' => $tsEnumMethodInstance->description ?? '',
                    'return' => $return,
                ];
            }
        }

        return $this;
    }

    protected function transformCaseKindsAndTypes(): self
    {
        if ($this->reflectionEnum->isBacked()) {
            $this->caseKinds = array_map(fn (array $case) => "'".$case['name']."'", $this->cases);
        }

        $this->caseTypes = array_map(function (array $case) {
            if (is_string($case['value'])) {
                return "'{$case['value']}'";
            }

            return $case['value'];
        }, $this->cases);

        return $this;
    }
}
