<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use PhpParser\Node\Expr;
use ReflectionClass;

/**
 * Shared building blocks for ExpressionHandler results.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type TypesImportMap from Datable
 *
 * @internal
 */
final class ValueResult
{
    /**
     * The fallback result for an expression that resolves to no useful type.
     *
     * @return ValueExpressionResult
     */
    public static function unknown(): array
    {
        return ['type' => 'unknown', 'optional' => false];
    }

    /**
     * Drop a top-level `| null` arm from a type string — a guarded success path proves it unreachable.
     * Nested null members (inside object shapes, generics, or array element types) are kept.
     *
     * The single canonical home for this helper: `CoalesceHandler` (stripping `??`'s left operand) and
     * `ConditionalMethodHandler` (stripping `whenNotNull()`'s success arm) both call it from here now.
     */
    public static function stripNullArm(string $type): string
    {
        $members = array_values(array_filter(
            TsTypeString::splitTopLevelUnion($type),
            fn (string $member): bool => $member !== 'null',
        ));

        return $members === [] ? 'unknown' : implode(' | ', $members);
    }

    /**
     * Suffix a type with `[]`, parenthesizing a union or intersection first: TypeScript binds `[]`
     * tighter than both, so `A & B[]` parses as `A & (B[])`, not `(A & B)[]`.
     *
     * FormRequestRulesAnalyzer's same-named method is a different rule (nesting-aware) and stays there.
     */
    public static function arrayWrapType(string $type): string
    {
        return str_contains($type, '|') || str_contains($type, '&') ? '('.$type.')[]' : $type.'[]';
    }

    /**
     * Whether every model a result names gets a published file; a framework or abstract model such as `Model` does not.
     *
     * A token with no file behind it would be emitted without an import, so the result declines instead.
     *
     * @param  ValueExpressionResult  $result
     */
    public static function namesOnlyPublishedModels(array $result): bool
    {
        $models = [...(isset($result['modelFqcn']) ? [$result['modelFqcn']] : []), ...($result['embeddedModelFqcns'] ?? [])];

        foreach ($models as $model) {
            if (str_starts_with($model, 'Illuminate\\') || new ReflectionClass($model)->isAbstract()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Merge multiple branch expressions into a single union-typed ValueExpressionResult.
     *
     * Null returns (guard clauses) contribute `null` to the union instead of a full object shape;
     * duplicate types are removed and import metadata is collected from all branches.
     *
     * @param  list<Expr>  $returns
     * @return ValueExpressionResult
     */
    public static function analyzeClosureUnion(array $returns, ExpressionEngine $engine, ?AnalysisScope $scope = null): array
    {
        $results = array_map($engine->resolve(...), $returns);

        // Paired with their expressions here because unionResults() receives results only, and by then
        // the Expr an audit has to name is gone. The arm is still dropped, never widened to `unknown`.
        foreach ($results as $index => $result) {
            if ($result['type'] === 'unknown') {
                DroppedUnionArms::record($returns[$index], $scope);
            }
        }

        return self::unionResults($results);
    }

    /**
     * Merge already-resolved branch results into a single union-typed ValueExpressionResult.
     *
     * A branch the engine could not type is dropped rather than widening the union to `unknown` (D1);
     * a caller that resolves its arms under its own narrowing enters here instead of resolving twice.
     *
     * @param  list<ValueExpressionResult>  $results
     * @return ValueExpressionResult
     */
    public static function unionResults(array $results): array
    {
        /** @var list<string> $types */
        $types = [];
        /** @var list<ValueExpressionResult> $branchResults every non-unknown branch, for channel merging */
        $branchResults = [];

        foreach ($results as $inner) {
            if ($inner['type'] === 'unknown') {
                continue; // @codeCoverageIgnore
            }

            $types[] = $inner['type'];
            $branchResults[] = $inner;
        }

        $types = array_values(array_unique($types));

        if ($types === []) {
            return self::unknown(); // @codeCoverageIgnore
        }

        return self::mergeUnion($types, $branchResults);
    }

    /**
     * Fold union member types and their branch results into one ValueExpressionResult, carrying every
     * FQCN/import channel across so no emitted token loses its import.
     *
     * Shared by the ternary/closure union and by coalesce, which computes its own member list.
     *
     * @param  list<string>  $types
     * @param  list<ValueExpressionResult>  $branchResults
     * @return ValueExpressionResult
     */
    public static function mergeUnion(array $types, array $branchResults): array
    {
        /** @var list<class-string> $enumResourceFqcns FQCNs from EnumResource::make() / new EnumResource() branches */
        $enumResourceFqcns = [];
        /** @var list<class-string> $enumDirectFqcns FQCNs from direct $this->prop enum-access branches */
        $enumDirectFqcns = [];
        // Never deduped: aliasPropertyType() walks this list positionally against left-to-right
        // occurrences of each bare enum name in the merged union's rendered type.
        /** @var list<class-string> $embeddedEnumFqcns FQCNs embedded inside nested inline-object types */
        $embeddedEnumFqcns = [];
        // One queue entry per rendered token, built in branch order because aliasPropertyType() walks it
        // against the rendered type left to right. A whole-branch model is appended in loop position, and
        // deduped: analyzeClosureUnion() collapses branches rendering the same string, leaving no token.
        /** @var list<class-string> $embeddedModelFqcns FQCN per model occurrence, in rendered order */
        $embeddedModelFqcns = [];
        /** @var list<class-string> $branchModelFqcns whole-branch model FQCNs already queued */
        $branchModelFqcns = [];
        /** @var list<class-string> $embeddedResourceFqcns */
        $embeddedResourceFqcns = [];
        /** @var TypesImportMap $customImports */
        $customImports = [];

        foreach ($branchResults as $inner) {
            // EnumResource branches are tracked apart from direct-access ones, so the result can
            // propagate the correct FQCN metadata.
            if (isset($inner['enumFqcn'])) {
                $enumResourceFqcns[] = $inner['enumFqcn'];
            }

            if (isset($inner['directEnumFqcn'])) {
                $enumDirectFqcns[] = $inner['directEnumFqcn'];
            }

            if (isset($inner['embeddedEnumFqcns'])) {
                array_push($embeddedEnumFqcns, ...$inner['embeddedEnumFqcns']);
            }

            if (isset($inner['modelFqcn']) && ! in_array($inner['modelFqcn'], $branchModelFqcns, true)) {
                $branchModelFqcns[] = $inner['modelFqcn'];
                $embeddedModelFqcns[] = $inner['modelFqcn'];
            }

            if (isset($inner['embeddedModelFqcns'])) {
                array_push($embeddedModelFqcns, ...$inner['embeddedModelFqcns']);
            }

            if (isset($inner['embeddedResourceFqcns'])) {
                array_push($embeddedResourceFqcns, ...$inner['embeddedResourceFqcns']);
            }

            if (isset($inner['resourceFqcn'])) {
                $embeddedResourceFqcns[] = $inner['resourceFqcn'];
            }

            foreach ($inner['customImports'] ?? [] as $path => $importTypes) {
                $customImports[$path] = [...($customImports[$path] ?? []), ...$importTypes];
            }
        }

        $result = ['type' => TsTypeString::hoistNull($types), 'optional' => false];

        $enumResourceFqcns = array_values(array_unique($enumResourceFqcns));
        $enumDirectFqcns = array_values(array_unique($enumDirectFqcns));
        $embeddedResourceFqcns = array_values(array_unique($embeddedResourceFqcns));

        if ($enumResourceFqcns !== []) {
            $allBranchFqcns = array_values(array_unique([...$enumResourceFqcns, ...$enumDirectFqcns]));

            if ($enumDirectFqcns === [] && count($enumResourceFqcns) === 1) {
                // Pure EnumResource, single FQCN.
                $result['enumFqcn'] = $enumResourceFqcns[0];
            } elseif ($enumDirectFqcns !== [] && count($allBranchFqcns) === 1) {
                // Mixed: same FQCN via EnumResource and via direct access.
                $result['enumFqcn'] = $allBranchFqcns[0];
                $result['directEnumFqcn'] = $allBranchFqcns[0];
            } elseif ($enumDirectFqcns === []
                && count($enumResourceFqcns) > 1
                && count($enumResourceFqcns) === count($types)
            ) {
                // All non-null branches are EnumResource with different FQCNs.
                // Emit ordered list so the transformer can do per-token AsEnum rewrite.
                $result['multiEnumResourceFqcns'] = $enumResourceFqcns;
            } else {
                // Multiple different FQCNs or complex mixed branches: fall back to embedded imports.
                // Never deduped, same positional reasoning as $embeddedEnumFqcns's own @var above.
                $embeddedEnumFqcns = [...$allBranchFqcns, ...$embeddedEnumFqcns];
            }
        } elseif ($enumDirectFqcns !== []) {
            // Only direct-access enum branches: existing embedded behaviour. Never deduped,
            // same positional reasoning as $embeddedEnumFqcns's own @var above.
            $embeddedEnumFqcns = [...$enumDirectFqcns, ...$embeddedEnumFqcns];
        }

        if ($embeddedEnumFqcns !== []) {
            $result['embeddedEnumFqcns'] = $embeddedEnumFqcns;
        }

        if ($embeddedModelFqcns !== []) {
            $result['embeddedModelFqcns'] = $embeddedModelFqcns;
        }

        if ($embeddedResourceFqcns !== []) {
            $result['embeddedResourceFqcns'] = $embeddedResourceFqcns;
        }

        if ($customImports !== []) {
            $result['customImports'] = $customImports;
        }

        return $result;
    }
}
