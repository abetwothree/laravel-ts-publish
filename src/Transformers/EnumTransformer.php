<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCase;
use AbeTwoThree\LaravelTsPublish\Attributes\TsEnum;
use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumMethod;
use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumStaticMethod;
use AbeTwoThree\LaravelTsPublish\Attributes\TsExclude;
use AbeTwoThree\LaravelTsPublish\Dtos\TsEnumDto;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\SnapshotsTransformerState;
use BackedEnum;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Override;
use ReflectionEnum;
use ReflectionMethod;
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
 * @phpstan-type PublishableMethod = array{
 *     method: ReflectionMethod,
 *     attribute: TsEnumMethod|TsEnumStaticMethod|null,
 *     params: array<string, mixed>,
 *     description: string,
 * }
 *
 * @extends CoreTransformer<UnitEnum|BackedEnum>
 */
class EnumTransformer extends CoreTransformer
{
    use SnapshotsTransformerState;

    /** @var list<string> */
    private const array BUILT_IN_ENUM_METHODS = ['cases', 'from', 'tryFrom'];

    public protected(set) string $enumName;

    public protected(set) string $description = '';

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
     * Get the transformed data as a structured DTO.
     */
    #[Override]
    public function data(): TsEnumDto
    {
        return new TsEnumDto(
            enumName: $this->enumName,
            description: $this->description,
            fqcn: $this->fqcn(),
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

    /**
     * The const and the types the enum's module declares.
     *
     * @return list<string>
     */
    #[Override]
    public function declaredNames(): array
    {
        $names = [$this->enumName, $this->enumName.'Type'];

        return $this->backed ? [...$names, $this->enumName.'Kind'] : $names;
    }

    /** @return list<string> */
    protected function transientProperties(): array
    {
        return ['reflectionEnum'];
    }

    protected function initInstance(): self
    {
        $this->reflectionEnum = new ReflectionEnum($this->findable);
        $this->backed = $this->reflectionEnum->isBacked();

        $tsEnum = ($this->reflectionEnum->getAttributes(TsEnum::class)[0] ?? null)?->newInstance();
        $this->enumName = TsNaming::enumName($this->findable);
        $this->description = $tsEnum !== null && $tsEnum->description !== ''
            ? $tsEnum->description
            : JsEmitter::parseDocBlockDescription($this->reflectionEnum->getDocComment());

        $this->filePath = $this->resolveRelativePath((string) $this->reflectionEnum->getFileName());
        $this->namespacePath = TsNaming::namespaceToPath($this->findable);

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

            $value = JsEmitter::enumScalar($caseValue);

            $description = $override['description'] ?? '';

            if ($description === '') {
                $constant = $this->reflectionEnum->getReflectionConstant($caseName);

                if ($constant !== false) {
                    $description = JsEmitter::parseDocBlockDescription($constant->getDocComment());
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

    /**
     * Publish each instance method's value for every case.
     */
    protected function transformMethods(): self
    {
        $autoInclude = Config::boolean('ts-publish.enums.auto_include_methods');
        $caseFormatting = Config::string('ts-publish.enums.method_case');

        foreach ($this->publishableMethods(TsEnumMethod::class, false, $autoInclude) as $publishable) {
            $method = $publishable['method'];

            // Stored as JSON data for every consumer; a case whose method throws, or whose value json_encode() cannot
            // write, publishes null. Keyed by the name transformCases() published for the case, as `_cases` lists it.
            $returns = [];
            foreach ($this->reflectionEnum->getCases() as $position => $case) {
                $caseName = $this->cases[$position]['name'];

                try {
                    $returns[$caseName] = JsEmitter::jsonValue(
                        $method->invokeArgs($case->getValue(), $publishable['params']),
                    );
                } catch (Throwable) {
                    $returns[$caseName] = null;
                }
            }

            $this->methods[$method->getName()] = [
                'name' => TsNaming::keyCase($publishable['attribute']?->name ?: $method->getName(), $caseFormatting),
                'description' => $publishable['description'],
                'returns' => $returns,
            ];
        }

        return $this;
    }

    /**
     * Publish each static method's value.
     */
    protected function transformStaticMethods(): self
    {
        $autoInclude = Config::boolean('ts-publish.enums.auto_include_static_methods');
        $caseFormatting = Config::string('ts-publish.enums.method_case');

        foreach ($this->publishableMethods(TsEnumStaticMethod::class, true, $autoInclude) as $publishable) {
            $method = $publishable['method'];

            // A static method does not depend on the case, so one call on the first case gives its value.
            $return = null;
            try {
                $case = $this->reflectionEnum->getCases()[0] ?? null;
                if ($case) {
                    $return = JsEmitter::jsonValue($method->invokeArgs($case->getValue(), $publishable['params']));
                }
            } catch (Throwable) {
                // A method that throws publishes null.
            }

            $this->staticMethods[$method->getName()] = [
                'name' => TsNaming::keyCase($publishable['attribute']?->name ?: $method->getName(), $caseFormatting),
                'description' => $publishable['description'],
                'return' => $return,
            ];
        }

        return $this;
    }

    /**
     * The enum's methods one attribute publishes, each with its attribute, call arguments and description.
     *
     * @param  class-string<TsEnumMethod>|class-string<TsEnumStaticMethod>  $attributeClass
     * @return list<PublishableMethod>
     */
    protected function publishableMethods(string $attributeClass, bool $static, bool $autoInclude): array
    {
        $publishable = [];

        foreach ($this->reflectionEnum->getMethods() as $method) {
            $attribute = $method->getAttributes($attributeClass)[0] ?? null;

            if ($attribute === null && ! ($autoInclude && $this->autoIncludes($method, $static))) {
                continue;
            }

            if ($method->getAttributes(TsExclude::class) !== []) {
                continue;
            }

            $instance = $attribute?->newInstance();
            $params = $instance->params ?? [];

            if ($method->getNumberOfRequiredParameters() > 0 && $params === []) {
                continue;
            }

            // Only an attributed method reaches here non-public: auto-include takes public methods alone.
            if (! $method->isPublic()) {
                AnalysisWarnings::addOnce($this->fqcn(), sprintf(
                    'Marks the %s method [%s] with #[%s], so it is not published. Make the method public to publish it.',
                    $method->isPrivate() ? 'private' : 'protected',
                    $method->getName(),
                    class_basename($attributeClass),
                ));

                continue;
            }

            $description = $instance->description ?? '';

            $publishable[] = [
                'method' => $method,
                'attribute' => $instance,
                'params' => $params,
                'description' => $description !== ''
                    ? $description
                    : JsEmitter::parseDocBlockDescription($method->getDocComment()),
            ];
        }

        return $publishable;
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

    /**
     * Whether auto-include takes the method: a public, non-magic method of the kind asked for, and no built-in static.
     */
    private function autoIncludes(ReflectionMethod $method, bool $static): bool
    {
        // A pure enum has no from() or tryFrom(), so it may declare an instance method of either name.
        return $method->isStatic() === $static
            && $method->isPublic()
            && ! str_starts_with($method->getName(), '__')
            && ! ($static && in_array($method->getName(), self::BUILT_IN_ENUM_METHODS, true));
    }
}
