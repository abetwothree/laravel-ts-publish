<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\Metadata;

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisImports;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\MethodLocator;
use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Concerns\ParsesTsCasts;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use ReflectionMethod;
use Throwable;

/**
 * Static type side of a model metadata companion: body inference with provide()'s Model parameter bound,
 * the @return array{...} shape, and method-level #[TsCasts], merged as inferred < docblock < casts.
 *
 * @phpstan-import-type TsCastsResult from ParsesTsCasts
 * @phpstan-import-type TypeSource from ModelMetadataAnalysis
 * @phpstan-import-type TypesImportMap from Datable
 *
 * @phpstan-type DeclaredTypes array{
 *     overrides: array<string, string>,
 *     requiredKeys: array<string, true>,
 *     optionalKeys: array<string, true>,
 * }
 */
class ModelMetadataAnalyzer
{
    use ParsesTsCasts;

    /**
     * Resolve every type the provider can emit, keeping inferred types only for keys the payload returned.
     *
     * @param  class-string<ModelMetadataProvider>  $providerClass
     * @param  list<string>  $payloadKeys
     */
    public function analyze(string $providerClass, array $payloadKeys, string $namespacePath = ''): ModelMetadataAnalysis
    {
        $method = new ReflectionMethod($providerClass, 'provide');
        $declared = $this->parseDeclaredTypes($method);
        $casts = $this->parseTsCasts($method);
        $analysis = $this->safeAnalyzeBody($method);
        $inferred = $analysis === null ? [] : $this->inferTypes($analysis, $payloadKeys);
        $result = $this->mergeTypes($inferred, $declared, $casts);

        if ($analysis === null) {
            return $result;
        }

        $inferredKeys = array_keys(array_filter($result->sources, static fn (string $source): bool => $source === 'inferred'));

        return new ModelMetadataAnalysis(
            $result->types,
            $result->sources,
            $result->requiredKeys,
            $result->importPaths,
            $this->inferredTypeImports($analysis, $method, $inferredKeys, $result->types, $namespacePath),
        );
    }

    /**
     * Analyze the body, or null when the engine fails — inference then contributes nothing.
     */
    protected function safeAnalyzeBody(ReflectionMethod $method): ?MethodAnalysis
    {
        try {
            return $this->analyzeBody($method);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Infer concrete, non-unknown types for the keys the payload returned.
     *
     * @param  list<string>  $payloadKeys
     * @return array<string, string>
     */
    protected function inferTypes(MethodAnalysis $analysis, array $payloadKeys): array
    {
        $types = [];

        foreach ($analysis->properties as $property) {
            if (! in_array($property['name'], $payloadKeys, true)
                || preg_match('/\bunknown\b/', $property['type']) === 1) {
                continue;
            }

            $types[$property['name']] = $property['type'];
        }

        return $types;
    }

    /**
     * Imports the inferred types need: only enum channels, only for keys inference won, only names still spelled.
     *
     * @param  list<string>  $inferredKeys
     * @param  array<string, string>  $types
     * @return TypesImportMap
     */
    protected function inferredTypeImports(
        MethodAnalysis $analysis,
        ReflectionMethod $method,
        array $inferredKeys,
        array $types,
        string $namespacePath,
    ): array {
        $this->forgetTsCastsCustomImports($analysis, $method);

        // A runtime metadata array need not satisfy a model interface, so a model-typed value keeps failing
        // the token check rather than importing a model; nested resources have no meaning in a provider.
        $analysis->modelFqcns = [];
        $analysis->nestedResources = [];

        foreach (array_keys($analysis->enumResources + $analysis->directEnumFqcns + $analysis->inlineEnumFqcns) as $name) {
            // DispatchesFqcnResults keys an embedded enum by its own FQCN, so key-equals-value marks a channel
            // with no property name to prune by; the still-spelled filter below owns those instead.
            if (in_array($name, $inferredKeys, true) || ($analysis->directEnumFqcns[$name] ?? null) === $name) {
                continue;
            }

            unset(
                $analysis->enumResources[$name], $analysis->directEnumFqcns[$name],
                $analysis->multiEnumResourceFqcns[$name], $analysis->inlineEnumFqcns[$name],
                $analysis->inlineEnumResourceFqcns[$name],
            );
        }

        $spelled = implode(' ', array_intersect_key($types, array_fill_keys($inferredKeys, true)));
        $imports = [];

        foreach (new AnalysisImports()->build($analysis, $namespacePath)['typeImports'] as $path => $names) {
            $used = array_values(array_filter(
                $names,
                static fn (string $name): bool => LaravelTsPublish::typeNameOccursIn($name, $spelled),
            ));

            if ($used !== []) {
                sort($used);
                $imports[$path] = $used;
            }
        }

        ksort($imports);

        return $imports;
    }

    /**
     * Drop the customImports ResourceAstAnalyzer::applyTsCastsFromMethod() appended for provide()'s own #[TsCasts].
     *
     * TsCastsImportResolver owns those imports (with alias collision handling); leaving them here would emit a
     * bare duplicate beside an aliased one.
     */
    protected function forgetTsCastsCustomImports(MethodAnalysis $analysis, ReflectionMethod $method): void
    {
        foreach ($method->getAttributes(TsCasts::class) as $attribute) {
            foreach ($attribute->newInstance()->types as $value) {
                if (! is_array($value) || ! isset($value['import'])) {
                    continue;
                }

                $names = LaravelTsPublish::extractImportableTypes($value['type']);
                $analysis->customImports[$value['import']] = array_values(array_diff(
                    $analysis->customImports[$value['import']] ?? [],
                    $names,
                ));

                if ($analysis->customImports[$value['import']] === []) {
                    unset($analysis->customImports[$value['import']]);
                }
            }
        }
    }

    /**
     * Run the engine over the body that declares provide(), with its Model parameter bound to its declared type.
     *
     * The declaring class is the subject so `self::` and `parent::` resolve against the file the body lives in.
     * Passing the context below short-circuits ResourceAstAnalyzer's parent walk, so nothing pins that today.
     */
    protected function analyzeBody(ReflectionMethod $method): MethodAnalysis
    {
        /** @var class-string $declaringClass */
        $declaringClass = $method->getDeclaringClass()->getName();
        $context = resolve(MethodLocator::class)->locate($declaringClass, $method->getName());

        if ($context === null) {
            return resolve(AstEngine::class)->analyzeMethod($declaringClass, $method->getName());
        }

        $scope = resolve(AstEngine::class)->bindingsFor($context);

        return new ResourceAstAnalyzer($context->reflection, null, $method->getName(), null, $scope, $context)->analyze();
    }

    /**
     * Parse the optional `@return array{...}` shape; a trailing `?` marks an optional key.
     *
     * @return DeclaredTypes
     */
    protected function parseDeclaredTypes(ReflectionMethod $method): array
    {
        $types = [];
        $requiredKeys = [];
        $optionalKeys = [];

        foreach (LaravelTsPublish::parseDocblockReturnArrayShape($method) as $property => $type) {
            $optional = str_ends_with($property, '?');
            $property = $optional ? substr($property, 0, -1) : $property;
            $types[$property] = $type;

            if ($optional) {
                $optionalKeys[$property] = true;
            } else {
                $requiredKeys[$property] = true;
            }
        }

        return ['overrides' => $types, 'requiredKeys' => $requiredKeys, 'optionalKeys' => $optionalKeys];
    }

    /**
     * Parse #[TsCasts] declared on provide().
     *
     * @return TsCastsResult
     */
    protected function parseTsCasts(ReflectionMethod $method): array
    {
        $castTypes = [];

        foreach ($method->getAttributes(TsCasts::class) as $attribute) {
            $castTypes = array_merge($castTypes, $attribute->newInstance()->types);
        }

        return $this->normalizeTsCasts($castTypes);
    }

    /**
     * Merge the three sources as inferred < docblock < casts, recording which source won each key.
     *
     * @param  array<string, string>  $inferred
     * @param  DeclaredTypes  $declared
     * @param  TsCastsResult  $casts
     */
    protected function mergeTypes(array $inferred, array $declared, array $casts): ModelMetadataAnalysis
    {
        $inferredOnly = array_diff_key($inferred, $declared['overrides'], $casts['overrides']);
        $types = [...$inferredOnly, ...$declared['overrides']];

        /** @var array<string, TypeSource> $sources */
        $sources = [
            ...array_fill_keys(array_keys($inferredOnly), 'inferred'),
            ...array_fill_keys(array_keys($declared['overrides']), 'docblock'),
        ];
        $requiredKeys = $declared['requiredKeys'];

        foreach ($casts['overrides'] as $property => $type) {
            $types[$property] = $type;
            $sources[$property] = 'casts';

            if ($casts['optionalOverrides'][$property] ?? isset($declared['optionalKeys'][$property])) {
                unset($requiredKeys[$property]);
            } else {
                $requiredKeys[$property] = true;
            }
        }

        return new ModelMetadataAnalysis($types, $sources, $requiredKeys, $casts['importPaths']);
    }
}
