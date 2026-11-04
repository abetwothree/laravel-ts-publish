<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\Metadata;

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\CastChannels;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\FileImportNames;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodLocator;
use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Concerns\ParsesTsCasts;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Support\ComposedClassTypes;
use AbeTwoThree\LaravelTsPublish\Support\ImportMaps;
use AbeTwoThree\LaravelTsPublish\Support\TsCastsImportResolver;
use ReflectionMethod;
use Throwable;

/**
 * Static type side of a model metadata companion: body inference with provide()'s Model parameter bound,
 * the @return array{...} shape, and method-level #[TsCasts], merged as inferred < docblock < casts.
 *
 * @phpstan-import-type TsCastsResult from ParsesTsCasts
 * @phpstan-import-type TypeSource from ModelMetadataAnalysis
 * @phpstan-import-type TypesImportMap from Datable
 * @phpstan-import-type CastMap from MethodAnalysis
 * @phpstan-import-type TokenFqcns from MethodAnalysis
 *
 * @phpstan-type ComposedTypes array{types: array<string, string>, typeImports: TypesImportMap}
 * @phpstan-type DeclaredTypes array{
 *     overrides: array<string, string>, imports: array<string, TypesImportMap>, tokens?: array<string, TokenFqcns>,
 *     requiredKeys: array<string, true>, optionalKeys: array<string, true>,
 * }
 */
class ModelMetadataAnalyzer
{
    use ParsesTsCasts;

    /**
     * Resolve every type the provider can emit, keeping inferred types only for keys the payload returned.
     *
     * @param  class-string<ModelMetadataProvider>  $providerClass  the class of the instance whose payload was
     *                                                              collected, which an inherited provide() runs as
     * @param  list<string>  $payloadKeys
     */
    public function analyze(string $providerClass, array $payloadKeys, string $namespacePath = ''): ModelMetadataAnalysis
    {
        $method = new ReflectionMethod($providerClass, 'provide');
        $declared = $this->parseDeclaredTypes($method);
        $casts = $this->parseTsCasts($method);
        $analysis = $this->safeAnalyzeBody($providerClass);

        $inferred = $analysis === null ? [] : $this->inferTypes($analysis, $payloadKeys);
        $result = $this->mergeTypes($inferred, $declared, $casts);

        if ($analysis === null) {
            return $result;
        }

        // A subclass's parseDeclaredTypes() may return the shape from before docblock keys carried their tokens.
        $file = $this->composeTypes($analysis, $method, $result, $declared['tokens'] ?? [], $payloadKeys, $namespacePath);

        return new ModelMetadataAnalysis(
            array_replace($result->types, $file['types']),
            $result->sources,
            $result->requiredKeys,
            $result->importPaths,
            $file['typeImports'],
            $result->docblockImports,
        );
    }

    /**
     * Analyze the body, or null when the engine fails — inference then contributes nothing.
     *
     * @param  class-string<ModelMetadataProvider>  $providerClass
     */
    protected function safeAnalyzeBody(string $providerClass): ?MethodAnalysis
    {
        try {
            return $this->analyzeBody($providerClass);
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
     * Fit the keys a cast or the docblock retypes, then respell every key no cast import owns, beside its imports.
     *
     * @param  array<string, TokenFqcns>  $docblockTokens  each docblock key's class behind each token of its text
     * @param  list<string>  $payloadKeys
     * @return ComposedTypes
     */
    protected function composeTypes(
        MethodAnalysis $analysis,
        ReflectionMethod $method,
        ModelMetadataAnalysis $result,
        array $docblockTokens,
        array $payloadKeys,
        string $namespacePath,
    ): array {
        $this->forgetTsCastsCustomImports($analysis, $method);

        // A runtime metadata array need not satisfy a model interface, so a model-typed value keeps failing
        // the token check rather than importing a model; nested resources have no meaning in a provider.
        $analysis->modelFqcns = [];
        $analysis->nestedResources = [];

        $payload = array_flip($payloadKeys);
        $published = array_intersect_key($result->types, $payload);

        foreach (array_keys($analysis->enumResources + $analysis->directEnumFqcns + $analysis->inlineEnumFqcns) as $name) {
            // DispatchesFqcnResults keys an embedded enum by its own FQCN, so key-equals-value marks a channel
            // with no property name to prune by; the still-spelled filter below owns those instead.
            if (isset($published[$name]) || ($analysis->directEnumFqcns[$name] ?? null) === $name) {
                continue;
            }

            unset(
                $analysis->enumResources[$name], $analysis->directEnumFqcns[$name],
                $analysis->multiEnumResourceFqcns[$name], $analysis->inlineEnumFqcns[$name],
                $analysis->inlineEnumResourceFqcns[$name],
            );
        }

        // The transformer throws for one name imported from two paths, so no cast may unbind another path's import.
        $customImports = $analysis->customImports;
        resolve(CastChannels::class)->fit($analysis, $this->castsInForce($analysis, $result, $docblockTokens, $published));
        $analysis->customImports = $customImports;

        $castPaths = array_intersect_key($result->importPaths, $payload);
        $casts = new TsCastsImportResolver()->resolve($published, $castPaths)['typeImports'];
        $declared = ImportMaps::merge($casts, ...array_values(array_intersect_key($result->docblockImports, $payload)));
        $brought = ImportMaps::localNames($declared);

        // A cast's own import owns its text, which TsCastsImportResolver respells.
        $keys = array_keys(array_diff_key($published, $castPaths));
        $members = [];

        foreach ($keys as $key) {
            $printed = [['key' => '', 'type' => $published[$key], 'source' => $key]];
            $members[] = ['type' => $published[$key], ...FileImportNames::objectQueues($analysis, $printed, brought: $brought)];
        }

        $file = FileImportNames::compose($members, ImportMaps::merge($analysis->customImports, $declared), $namespacePath);

        return [
            'types' => array_combine($keys, $file['types']),
            'typeImports' => $this->inferredOnlyImports($file['typeImports'], $declared, $analysis, $namespacePath),
        ];
    }

    /**
     * Drop the names a cast or a docblock key imports, which the transformer merges itself, and put each composed
     * class's names back under its marker, which the transformer reads its composed classes from.
     *
     * @param  TypesImportMap  $imports
     * @param  TypesImportMap  $declared
     * @return TypesImportMap
     */
    protected function inferredOnlyImports(array $imports, array $declared, MethodAnalysis $analysis, string $namespacePath): array
    {
        foreach ($imports as $path => $names) {
            $own = array_diff($declared[$path] ?? [], $analysis->customImports[$path] ?? []);
            $imports[$path] = array_values(array_diff($names, $own));
        }

        foreach ($analysis->customImports as $marker => $names) {
            if (! ComposedClassTypes::isMarker((string) $marker)) {
                continue;
            }

            $path = ComposedClassTypes::importPath((string) $marker, $namespacePath);
            $spelled = array_values(array_intersect($names, $imports[$path] ?? []));
            $imports[$path] = array_values(array_diff($imports[$path] ?? [], $spelled));

            if ($spelled !== []) {
                $imports[$marker] = $spelled;
            }
        }

        ksort($imports);

        return array_filter($imports, static fn (array $names): bool => $names !== []);
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

                $names = TsTypeString::extractImportableTypes($value['type']);
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
     * Run the engine over provide() as the provider runs it, with its Model parameter bound to its declared type.
     *
     * Located on the provider, an inherited or trait body reads `$this` and `static` as the provider and `self` as
     * its declaring class. The provider is the known runtime class: the transformer passes its instance's class.
     *
     * @param  class-string<ModelMetadataProvider>  $providerClass
     */
    protected function analyzeBody(string $providerClass): MethodAnalysis
    {
        $context = resolve(MethodLocator::class)->locate($providerClass, 'provide');

        if ($context === null) {
            return resolve(AstEngine::class)->analyzeMethod($providerClass, 'provide');
        }

        $scope = resolve(AstEngine::class)->bindingsFor($context);

        return new ResourceAstAnalyzer($context->reflection, null, 'provide', null, $scope, $context)->analyze();
    }

    /**
     * Parse the optional `@return array{...}` shape, with the custom imports of the overrides each key spells, none for
     * a key naming any class but an enum at any depth; a trailing `?` marks an optional key.
     *
     * @return DeclaredTypes
     */
    protected function parseDeclaredTypes(ReflectionMethod $method): array
    {
        $types = [];
        $imports = [];
        $tokens = [];
        $requiredKeys = [];
        $optionalKeys = [];

        foreach (LaravelTsPublish::docblockReturnShapeInfos($method) as $property => $info) {
            $property = (string) $property;
            $optional = str_ends_with($property, '?');
            $property = $optional ? substr($property, 0, -1) : $property;
            $types[$property] = $info['type'];
            $tokens[$property] = $info['tokenFqcns'] ?? [];

            // A companion imports no class but an enum, so an override's import of a class's name must not let it pass.
            $namesClass = array_any($tokens[$property], fn (?string $fqcn): bool => $fqcn !== null && ! enum_exists($fqcn));
            $imports[$property] = $namesClass ? [] : $info['customImports'];

            if ($optional) {
                $optionalKeys[$property] = true;
            } else {
                $requiredKeys[$property] = true;
            }
        }

        return [
            'overrides' => $types,
            'imports' => $imports,
            'tokens' => $tokens,
            'requiredKeys' => $requiredKeys,
            'optionalKeys' => $optionalKeys,
        ];
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
            $castTypes = array_replace($castTypes, $attribute->newInstance()->types);
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
        $types = array_replace($inferredOnly, $declared['overrides']);

        /** @var array<string, TypeSource> $sources */
        $sources = array_replace(
            array_fill_keys(array_keys($inferredOnly), 'inferred'),
            array_fill_keys(array_keys($declared['overrides']), 'docblock'),
        );
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

        // A key the docblock still types brings its overrides' custom imports; one a cast replaces brings none.
        $docblockKeys = array_filter($sources, fn (string $source): bool => $source === 'docblock');

        return new ModelMetadataAnalysis(
            $types,
            $sources,
            $requiredKeys,
            $casts['importPaths'],
            docblockImports: array_intersect_key($declared['imports'], $docblockKeys),
        );
    }

    /**
     * The cast in force for each published key a helper's cast, provide()'s #[TsCasts] or the docblock retypes.
     *
     * A docblock key names only the enums behind its tokens, since a companion imports no model.
     *
     * @param  array<string, TokenFqcns>  $docblockTokens
     * @param  array<string, string>  $published  each payload key's type
     * @return CastMap
     */
    private function castsInForce(
        MethodAnalysis $analysis,
        ModelMetadataAnalysis $result,
        array $docblockTokens,
        array $published,
    ): array {
        $inForce = array_intersect_key($analysis->casts, $published);

        foreach ($published as $key => $type) {
            if ($result->sources[$key] === 'casts') {
                $inForce[$key] = ['type' => $type, 'import' => $result->importPaths[$key] ?? null];
            } elseif ($result->sources[$key] === 'docblock') {
                $inForce[$key] = [
                    'type' => $type,
                    'import' => null,
                    'customImports' => $result->docblockImports[$key] ?? [],
                    'tokenFqcns' => array_map(
                        static fn (?string $fqcn): ?string => $fqcn !== null && enum_exists($fqcn) ? $fqcn : null,
                        $docblockTokens[$key] ?? [],
                    ),
                ];
            }
        }

        return $inForce;
    }
}
