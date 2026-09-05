<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\Metadata;

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\MethodLocator;
use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Concerns\ParsesTsCasts;
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
    public function analyze(string $providerClass, array $payloadKeys): ModelMetadataAnalysis
    {
        $method = new ReflectionMethod($providerClass, 'provide');

        return $this->mergeTypes(
            $this->inferTypes($method, $payloadKeys),
            $this->parseDeclaredTypes($method),
            $this->parseTsCasts($method),
        );
    }

    /**
     * Infer concrete, non-unknown types from the provide() body; an engine failure infers nothing, never fails the run.
     *
     * @param  list<string>  $payloadKeys
     * @return array<string, string>
     */
    protected function inferTypes(ReflectionMethod $method, array $payloadKeys): array
    {
        try {
            $analysis = $this->analyzeBody($method);
        } catch (Throwable) {
            return [];
        }

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
     * Run the engine over the body that declares provide(), with its Model parameter bound to its declared type.
     *
     * The declaring class is the subject: ResourceAstAnalyzer walks to a parent without the seeded scope, so an
     * inherited body analyzed from the subclass would lose the binding.
     */
    protected function analyzeBody(ReflectionMethod $method): MethodAnalysis
    {
        /** @var class-string $declaringClass */
        $declaringClass = $method->getDeclaringClass()->getName();
        $context = resolve(MethodLocator::class)->locateOwn($declaringClass, $method->getName());

        if ($context === null) {
            return resolve(AstEngine::class)->analyzeMethod($declaringClass, $method->getName());
        }

        $scope = resolve(AstEngine::class)->bindingsFor($context);

        return new ResourceAstAnalyzer($context->reflection, null, $method->getName(), null, $scope)->analyze();
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
