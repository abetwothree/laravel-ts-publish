<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;

/**
 * Fan a value expression's FQCN channels out into the caller's per-key tracking maps.
 *
 * Kept apart from MethodAnalysis::addProperty(), its only caller, so the channel routing reads as
 * one table rather than as a tail on the property append.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type ClassMapType from MethodAnalysis
 * @phpstan-import-type MultiEnumFqcnsMap from MethodAnalysis
 * @phpstan-import-type EnumResourceArmShapeMap from MethodAnalysis
 *
 * @internal
 */
trait DispatchesFqcnResults
{
    /**
     * Dispatch FQCN results from a value expression into the tracking maps.
     *
     * @param  ValueExpressionResult  $result
     * @param  ClassMapType  $enumResources
     * @param  ClassMapType  $directEnumFqcns
     * @param  ClassMapType  $nestedResources
     * @param  ClassMapType  $modelFqcns
     * @param  MultiEnumFqcnsMap  $multiEnumResourceFqcns
     * @param  EnumResourceArmShapeMap  $enumResourceArmShapes
     */
    protected function dispatchFqcnResults(
        string $keyName,
        array $result,
        array &$enumResources,
        array &$directEnumFqcns,
        array &$nestedResources,
        array &$modelFqcns,
        array &$multiEnumResourceFqcns = [],
        array &$enumResourceArmShapes = [],
    ): void {
        if (isset($result['enumFqcn'])) {
            $enumResources[$keyName] = $result['enumFqcn'];
        }

        if (isset($result['directEnumFqcn'])) {
            $directEnumFqcns[$keyName] = $result['directEnumFqcn'];
        }

        // Only a mixed ternary/Elvis arm (TernaryHandler) records these; the merged union's own
        // 'type' string already deduped away which arm was the collection.
        if (isset($result['wrapIsCollection'], $result['directIsArray'])) {
            $enumResourceArmShapes[$keyName] = [
                'wrapIsCollection' => $result['wrapIsCollection'],
                'directIsArray' => $result['directIsArray'],
            ];
        }

        if (isset($result['multiEnumResourceFqcns'])) {
            $multiEnumResourceFqcns[$keyName] = $result['multiEnumResourceFqcns'];
        }

        if (isset($result['resourceFqcn'])) {
            $nestedResources[$keyName] = $result['resourceFqcn'];
        }

        if (isset($result['modelFqcn'])) {
            $modelFqcns[$keyName] = $result['modelFqcn'];
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
