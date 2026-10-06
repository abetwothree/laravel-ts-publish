<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedClasses;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedModelRegistry;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\ClassTokenQueue;
use Closure;
use PhpParser\Node\Expr;
use ReflectionClass;

/**
 * Shared building blocks for ExpressionHandler results.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type TypesImportMap from Datable
 *
 * @phpstan-type AttributeChannels = array{
 *      enumFqcns: list<class-string>,
 *      classFqcns: list<class-string>,
 *      classTokenFqcns?: list<class-string>,
 *      customImports?: TypesImportMap
 * }
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
     * Whether a type has a top-level `null` arm; one nested in a shape, a generic or an element type does not count.
     */
    public static function hasNullArm(string $type): bool
    {
        return in_array('null', TsTypeString::splitTopLevelUnion($type), true);
    }

    /**
     * Append a top-level `| null` arm unless the type has one; `unknown` already admits null, so it stays as is.
     *
     * Never TsTypeString::hoistNull(): it de-duplicates members, so `User | User`, two classes, would lose one.
     */
    public static function withNullArm(string $type): string
    {
        return $type === 'unknown' || self::hasNullArm($type) ? $type : $type.' | null';
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
     * Whether a generated file exports every class a result names on its model channels, as far as this run knows.
     *
     * A token with no file behind it would be emitted without an import, so MethodAnalysis::addProperty() declines it.
     *
     * @param  ValueExpressionResult  $result
     */
    public static function namesOnlyExportedClasses(array $result): bool
    {
        foreach (self::modelChannelFqcns($result) as $fqcn) {
            if (! PublishedClasses::exports($fqcn)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether every model a result names gets a published file. With no published set to read, a framework or
     * abstract model such as `Model` is still known to have none.
     *
     * @param  ValueExpressionResult  $result
     */
    public static function namesOnlyPublishedModels(array $result): bool
    {
        if (! self::namesOnlyExportedClasses($result)) {
            return false;
        }

        if (! PublishedModelRegistry::isEmpty()) {
            return true;
        }

        foreach (self::modelChannelFqcns($result) as $model) {
            if (str_starts_with($model, 'Illuminate\\') || new ReflectionClass($model)->isAbstract()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Carry a model attribute's FQCN channels onto the result that reads it, so every class, enum and `#[TsType]` name
     * the attribute's type spells keeps its import wherever the read is published.
     *
     * One FQCN of a kind rides its single-entry channel and several ride the embedded one, as `$this->attr` reads do.
     *
     * @param  ValueExpressionResult  $result
     * @param  AttributeChannels  $attribute
     * @return ValueExpressionResult
     */
    public static function withAttributeChannels(array $result, array $attribute): array
    {
        if (count($attribute['enumFqcns']) > 1) {
            $result['embeddedEnumFqcns'] = $attribute['enumFqcns'];
        } elseif ($attribute['enumFqcns'] !== []) {
            $result['directEnumFqcn'] = $attribute['enumFqcns'][0];
        }

        if (count($attribute['classFqcns']) > 1) {
            $result['embeddedModelFqcns'] = ClassTokenQueue::fqcnsOf($attribute);
        } elseif ($attribute['classFqcns'] !== []) {
            $result['modelFqcn'] = $attribute['classFqcns'][0];
        }

        if (($attribute['customImports'] ?? []) !== []) {
            $result['customImports'] = $attribute['customImports'];
        }

        return $result;
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
    public static function analyzeClosureUnion(array $returns, ExpressionEngine $engine, AnalysisScope $scope): array
    {
        $results = array_map($engine->resolve(...), $returns);

        // Paired with their expressions here because unionResults() receives results only, and by then
        // the Expr an audit has to name is gone. The arm is still dropped, never widened to `unknown`.
        foreach ($results as $index => $result) {
            if ($result['type'] === 'unknown') {
                DroppedUnionArms::record($returns[$index], $scope, 'closure-union');
            }
        }

        return self::unionResults($results);
    }

    /**
     * Merge already-resolved branch results into a single union-typed ValueExpressionResult.
     *
     * A branch the engine could not type is dropped rather than widening the union to `unknown` (D1);
     * a caller that resolves its arms under its own narrowing enters here instead of resolving twice.
     * With $countMembers, a union of enum resources is recognised by the count of its members, not of its arms' types.
     *
     * @param  list<ValueExpressionResult>  $results
     * @return ValueExpressionResult
     */
    public static function unionResults(array $results, bool $countMembers = false): array
    {
        /** @var list<string> $types the types mergeUnion() counts: each arm's whole type, or each member of it */
        $types = [];
        /** @var list<ValueExpressionResult> $branchResults every non-unknown branch, for channel merging */
        $branchResults = [];

        foreach ($results as $inner) {
            if ($inner['type'] === 'unknown') {
                continue;
            }

            array_push($types, ...($countMembers ? TsTypeString::splitTopLevelUnion($inner['type']) : [$inner['type']]));
            $branchResults[] = $inner;
        }

        $types = array_values(array_unique($types));

        if ($types === []) {
            return self::unknown();
        }

        $result = self::mergeUnion($types, $branchResults);

        // Arms that spell one name for two classes cannot be told apart by their text, so the members are read again
        // arm by arm, one member per class.
        return self::spellsTwoClassesAlike($branchResults) ? self::withMembersByClass($result, $branchResults) : $result;
    }

    /**
     * The result's model queue, when two of its models share a name and the queue does not outrun the type's tokens.
     *
     * Reads the model channels only. ResultTypeInfoBridge hands the queue on as a type info's classTokenFqcns.
     *
     * @param  ValueExpressionResult  $result
     * @return list<class-string>|null
     */
    public static function modelQueueByToken(array $result): ?array
    {
        $models = self::modelChannelFqcns($result);
        $nameOf = class_basename(...);

        if (! self::sharesAName($models, $nameOf)) {
            return null;
        }

        $queue = new ClassTokenQueue($models, $nameOf);
        $queue->take($result['type']);

        return $queue->outrunsItsTokens() ? null : $models;
    }

    /**
     * Whether one name spells two different models, or two different resources, among the given results.
     *
     * @param  list<ValueExpressionResult>  $results
     */
    private static function spellsTwoClassesAlike(array $results): bool
    {
        /** @var list<class-string> $models */
        $models = [];
        /** @var list<class-string> $resources */
        $resources = [];

        foreach ($results as $result) {
            array_push($models, ...self::modelChannelFqcns($result));
            array_push($resources, ...self::resourceChannelFqcns($result));
        }

        return self::sharesAName($models, class_basename(...))
            || self::sharesAName($resources, static fn (string $fqcn): string => TsNaming::resourceTypeName($fqcn));
    }

    /**
     * Whether two different classes of the list are spelled with one name.
     *
     * @param  list<class-string>  $fqcns
     * @param  Closure(class-string): string  $nameOf  the name a class's token is spelled with
     */
    private static function sharesAName(array $fqcns, Closure $nameOf): bool
    {
        /** @var array<string, class-string> $firstOf name => the first class seen under it */
        $firstOf = [];

        foreach ($fqcns as $fqcn) {
            if (($firstOf[$nameOf($fqcn)] ??= $fqcn) !== $fqcn) {
                return true;
            }
        }

        return false;
    }

    /**
     * The FQCNs a result carries on its single and embedded model channels.
     *
     * @param  ValueExpressionResult  $result
     * @return list<class-string>
     */
    private static function modelChannelFqcns(array $result): array
    {
        return [...(isset($result['modelFqcn']) ? [$result['modelFqcn']] : []), ...($result['embeddedModelFqcns'] ?? [])];
    }

    /**
     * Fold union member types and their branch results into one ValueExpressionResult, carrying every
     * FQCN/import channel across so no emitted token loses its import.
     *
     * @param  list<string>  $types
     * @param  list<ValueExpressionResult>  $branchResults
     * @return ValueExpressionResult
     */
    private static function mergeUnion(array $types, array $branchResults): array
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
        // Safe to dedupe: one class per name reads alike at every entry, and two under one name are read by class
        // unless an arm outruns its tokens, where the merge by text stands.
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

    /**
     * Give a union one member per class where its arms spell one name for two: a member repeats an earlier one only
     * when its text and the classes behind its tokens both match, and the queues carry one FQCN per token, in order.
     * The merge by text stands when an arm's own queue does not line up with its tokens.
     *
     * @param  ValueExpressionResult  $union  the arms as merged by their text
     * @param  list<ValueExpressionResult>  $arms
     * @return ValueExpressionResult
     */
    private static function withMembersByClass(array $union, array $arms): array
    {
        /** @var array<string, string> $members identity => text */
        $members = [];
        $nullable = false;
        /** @var list<class-string> $modelFqcns */
        $modelFqcns = [];
        /** @var list<class-string> $resourceFqcns */
        $resourceFqcns = [];

        foreach ($arms as $arm) {
            $models = new ClassTokenQueue(self::modelChannelFqcns($arm), class_basename(...));
            $resources = new ClassTokenQueue(
                self::resourceChannelFqcns($arm),
                static fn (string $fqcn): string => TsNaming::resourceTypeName($fqcn),
            );

            foreach (TsTypeString::splitTopLevelUnion($arm['type']) as $member) {
                if ($member === 'null') {
                    $nullable = true;

                    continue;
                }

                $memberModels = $models->take($member);
                $memberResources = $resources->take($member);
                $identity = implode("\0", [$member, ...$memberModels, '', ...$memberResources]);

                if (isset($members[$identity])) {
                    continue;
                }

                $members[$identity] = $member;
                array_push($modelFqcns, ...$memberModels);
                array_push($resourceFqcns, ...$memberResources);
            }

            // An arm that queues a name for two classes more often than it spells it holds a class behind another's
            // token, and does not say which token is whose (a merge by text is such an arm): the merge by text stands.
            if ($models->outrunsItsTokens() || $resources->outrunsItsTokens()) {
                return $union;
            }
        }

        unset($union['embeddedModelFqcns'], $union['embeddedResourceFqcns']);

        $union['type'] = implode(' | ', [...array_values($members), ...($nullable ? ['null'] : [])]);

        if ($modelFqcns !== []) {
            $union['embeddedModelFqcns'] = $modelFqcns;
        }

        if ($resourceFqcns !== []) {
            $union['embeddedResourceFqcns'] = $resourceFqcns;
        }

        return $union;
    }

    /**
     * The FQCNs a result carries on its single and embedded resource channels.
     *
     * @param  ValueExpressionResult  $result
     * @return list<class-string>
     */
    private static function resourceChannelFqcns(array $result): array
    {
        return [...(isset($result['resourceFqcn']) ? [$result['resourceFqcn']] : []), ...($result['embeddedResourceFqcns'] ?? [])];
    }
}
