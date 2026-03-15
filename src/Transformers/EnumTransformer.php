<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCase;
use AbeTwoThree\LaravelTsPublish\Attributes\TsEnum;
use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumMethod;
use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumStaticMethod;
use AbeTwoThree\LaravelTsPublish\Dtos\TsEnumDto;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use BackedEnum;
use Illuminate\Support\Str;
use Override;
use ReflectionEnum;
use Throwable;
use UnitEnum;

/**
 * @phpstan-import-type CasesList from TsEnumDto
 * @phpstan-import-type MethodsList from TsEnumDto
 * @phpstan-import-type StaticMethodsList from TsEnumDto
 * @phpstan-import-type CaseKindsList from TsEnumDto
 * @phpstan-import-type CaseTypesList from TsEnumDto
 *
 * @phpstan-type TsTypeOverrides = array<string, array{name?: string, value?: string|int, description?: string}>
 *
 * @extends CoreTransformer<UnitEnum|BackedEnum>
 */
class EnumTransformer extends CoreTransformer
{
    public protected(set) string $enumName;

    public protected(set) string $description = '';

    public protected(set) string $filePath;

    public protected(set) string $namespacePath;

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

    /** @var list<string> */
    private const array BUILT_IN_ENUM_METHODS = ['cases', 'from', 'tryFrom'];

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
     * Get the transformed data as a structured DTO.
     */
    #[Override]
    public function data(): TsEnumDto
    {
        return new TsEnumDto(
            enumName: $this->enumName,
            description: $this->description,
            filePath: $this->filePath,
            filename: $this->filename(),
            cases: $this->cases,
            methods: $this->methods,
            staticMethods: $this->staticMethods,
            caseKinds: $this->caseKinds,
            caseTypes: $this->caseTypes,
            backed: $this->backed,
        );
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

        $tsEnumAttrs = $this->reflectionEnum->getAttributes(TsEnum::class);

        if ($tsEnumAttrs) {
            $tsEnumInstance = $tsEnumAttrs[0]->newInstance();
            $this->enumName = $tsEnumInstance->name;
            $this->description = $tsEnumInstance->description !== ''
                ? $tsEnumInstance->description
                : LaravelTsPublish::parseDocBlockDescription($this->reflectionEnum->getDocComment());
        } else {
            $this->enumName = $this->reflectionEnum->getShortName();
            $this->description = LaravelTsPublish::parseDocBlockDescription($this->reflectionEnum->getDocComment());
        }

        $this->filePath = $this->resolveRelativePath((string) $this->reflectionEnum->getFileName());
        $this->namespacePath = LaravelTsPublish::namespaceToPath($this->findable);

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

            $description = $override['description'] ?? '';

            if ($description === '') {
                $constant = $this->reflectionEnum->getReflectionConstant($caseName);

                if ($constant !== false) {
                    $description = LaravelTsPublish::parseDocBlockDescription($constant->getDocComment());
                }
            }

            $this->cases[] = [
                'name' => $override['name'] ?? $caseName,
                'value' => $override['value'] ?? $value,
                'description' => $description,
            ];
        }

        return $this;
    }

    protected function transformMethods(): self
    {
        $autoInclude = config('ts-publish.auto_include_enum_methods');
        $caseFormatting = config()->string('ts-publish.enum_method_case');

        foreach ($this->reflectionEnum->getMethods() as $method) {
            $methodName = $method->getName();

            $tsEnumMethodAttribute = $method->getAttributes(TsEnumMethod::class)[0] ?? null;

            if (! $tsEnumMethodAttribute) {
                if (! $autoInclude
                    || $method->isStatic()
                    || ! $method->isPublic()
                    || str_starts_with($methodName, '__')
                ) {
                    continue;
                }
            }

            /** @var TsEnumMethod|null $tsEnumMethodInstance */
            $tsEnumMethodInstance = $tsEnumMethodAttribute?->newInstance();

            $hasRequiredParams = $method->getNumberOfRequiredParameters() > 0;
            $attributeParams = $tsEnumMethodInstance->params ?? [];

            if ($hasRequiredParams && $attributeParams === []) {
                continue;
            }

            $description = $tsEnumMethodInstance->description ?? '';

            if ($description === '') {
                $description = LaravelTsPublish::parseDocBlockDescription($method->getDocComment());
            }

            // get the returns, we need to call the method with each case instance and collect the return values to know which TS types to import
            $returns = [];
            foreach ($this->reflectionEnum->getCases() as $case) {
                try {
                    $caseInstance = $case->getValue();
                    $returns[$case->getName()] = $method->invokeArgs($caseInstance, $attributeParams);
                } catch (Throwable) {
                    $returns[$case->getName()] = null;
                }
            }

            $this->methods[$methodName] = [
                'name' => LaravelTsPublish::keyCase($tsEnumMethodInstance?->name ?: $methodName, $caseFormatting),
                'description' => $description,
                'returns' => $returns,
            ];
        }

        return $this;
    }

    protected function transformStaticMethods(): self
    {
        $autoInclude = config('ts-publish.auto_include_enum_static_methods');
        $caseFormatting = config()->string('ts-publish.enum_method_case');

        foreach ($this->reflectionEnum->getMethods() as $method) {
            $methodName = $method->getName();

            $tsEnumMethodAttribute = $method->getAttributes(TsEnumStaticMethod::class)[0] ?? null;

            if (! $tsEnumMethodAttribute) {
                if (! $autoInclude
                    || ! $method->isStatic()
                    || ! $method->isPublic()
                    || str_starts_with($methodName, '__')
                    || in_array($methodName, self::BUILT_IN_ENUM_METHODS, true)
                ) {
                    continue;
                }
            }

            /** @var TsEnumStaticMethod|null $tsEnumMethodInstance */
            $tsEnumMethodInstance = $tsEnumMethodAttribute?->newInstance();

            $hasRequiredParams = $method->getNumberOfRequiredParameters() > 0;
            $attributeParams = $tsEnumMethodInstance->params ?? [];

            if ($hasRequiredParams && $attributeParams === []) {
                continue;
            }

            $description = $tsEnumMethodInstance->description ?? '';

            if ($description === '') {
                $description = LaravelTsPublish::parseDocBlockDescription($method->getDocComment());
            }

            // For methods, we just call it once and get the return value. It should be a primitive or an array of primitives that can be transformed to JavaScript for functional use.
            $return = null;
            try {
                $case = $this->reflectionEnum->getCases()[0] ?? null;
                if ($case) {
                    $caseInstance = $case->getValue();
                    $return = $method->invokeArgs($caseInstance, $attributeParams);
                }
            } catch (Throwable $e) {
                // If the method requires parameters or something else goes wrong, we just ignore the return value
            }

            $this->staticMethods[$methodName] = [
                'name' => LaravelTsPublish::keyCase($tsEnumMethodInstance?->name ?: $methodName, $caseFormatting),
                'description' => $description,
                'return' => $return,
            ];
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
