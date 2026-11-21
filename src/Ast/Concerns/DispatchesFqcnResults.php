<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;

/**
 * Fan a value expression's FQCN channels out into the caller's per-key tracking maps.
 *
 * Kept apart from MethodAnalysis::addProperty(), its only caller, so the channel routing reads as
 * one table rather than as a tail on the property append.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type ClassMapType from MethodAnalysis
 * @phpstan-import-type WrappedEnumsMap from MethodAnalysis
 *
 * @internal
 */
trait DispatchesFqcnResults
{
    /**
     * Dispatch FQCN results from a value expression into the tracking maps.
     *
     * @param  ValueExpressionResult  $result
     * @param  ClassMapType  $directEnumFqcns
     * @param  ClassMapType  $nestedResources
     * @param  ClassMapType  $modelFqcns
     * @param  WrappedEnumsMap  $wrappedEnums
     */
    protected function dispatchFqcnResults(
        string $keyName,
        array $result,
        array &$directEnumFqcns,
        array &$nestedResources,
        array &$modelFqcns,
        array &$wrappedEnums,
    ): void {
        if (isset($result['directEnumFqcn'])) {
            $directEnumFqcns[$keyName] = $result['directEnumFqcn'];
        }

        if (isset($result['resourceFqcn'])) {
            $nestedResources[$keyName] = $result['resourceFqcn'];
        }

        if (isset($result['modelFqcn'])) {
            $modelFqcns[$keyName] = $result['modelFqcn'];
        }

        if (($result['wrappedEnumFqcns'] ?? []) !== []) {
            $wrappedEnums[$keyName] = [...$wrappedEnums[$keyName] ?? [], ...$result['wrappedEnumFqcns']];
        }

        // Embedded FQCNs from inline relation filter types (e.g. $this->post->only([...])).
        // Using FQCN as both key and value: ResourceTransformer only reads the value, never the key.
        foreach ($result['embeddedEnumFqcns'] ?? [] as $fqcn) {
            $directEnumFqcns[$fqcn] = $fqcn;
        }

        foreach ($result['embeddedModelFqcns'] ?? [] as $fqcn) {
            $modelFqcns[$fqcn] = $fqcn;
        }

        foreach ($result['embeddedResourceFqcns'] ?? [] as $fqcn) {
            $nestedResources[$fqcn] = $fqcn;
        }
    }
}
