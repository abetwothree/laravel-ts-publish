<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\FiltersAttributeKeys;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesFilteredRelationTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesMapProxyElementModels;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesModelRelationTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverMethodReturnResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Identifier;
use ReflectionMethod;

/**
 * `$this->relation->only([...])`/`->except([...])`, also read through `$this->resource`, and Laravel's `map`
 * HigherOrderCollectionProxy filter (`$var->map->only([...])`/`->except([...])`) — relation/collection filters.
 *
 * The relation arm declines what it cannot type, such as a model whose filter override reflection types. Once the
 * map-proxy arm matches, it claims `unknown` for no element model, such an override, no literal keys, or keys naming
 * nothing. A scope that carries no import gets answers naming no model or enum.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type TypesImportMap from Datable
 *
 * @internal
 */
final class RelationFilterHandler implements ExpressionHandler
{
    use FiltersAttributeKeys;
    use InspectsAstNodes;
    use ResolvesFilteredRelationTypes;
    use ResolvesMapProxyElementModels;
    use ResolvesModelRelationTypes;

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [MethodCall::class, NullsafeMethodCall::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if (! ($expr instanceof MethodCall || $expr instanceof NullsafeMethodCall)
            || ! $this->callsAttributeFilter($expr)
            || ! $expr->var instanceof PropertyFetch
        ) {
            return null;
        }

        // $this->relation->only([...]), $this->relation?->only([...]), or either through $this->resource
        if ($this->isModelMemberFetch($expr->var, $scope)) {
            $result = $this->analyzeRelationFilter($expr, $scope, $engine);

            if ($result !== null) {
                return $result;
            }
        }

        // $var->map->only([...]) / ->except([...]) — Laravel's HigherOrderCollectionProxy on `map`: call the filter on
        // every element and collect the results. `$this->resource->map` reaches here once no member `map` answers.
        if ($expr->var->name instanceof Identifier && $expr->var->name->toString() === 'map') {
            return $this->analyzeMapProxyFilter($expr, $scope);
        }

        return null;
    }

    /**
     * Whether a fetch reads a member of the scope's model: `$this->prop`, or `$this->resource->prop` in a resource.
     *
     * `$this->resource` itself is the model, not a member named `resource`; its filters belong to the receiver rules.
     * Only a subject that forwards to its model has that proxy: in a model's own body it is the model's own member.
     */
    private function isModelMemberFetch(PropertyFetch $fetch, AnalysisScope $scope): bool
    {
        return ($this->isThisPropertyFetch($fetch) && ! $this->isResourceFetch($fetch))
            || ($this->isResourceFetch($fetch->var) && $scope->forwardsUndeclaredMembersTo !== null);
    }

    /**
     * Analyze `$this->relation->only([...])` or `$this->relation?->only([...])`, declining when nothing types it.
     *
     * A many-relation publishes its own read, since `Eloquent\Collection::only()` keeps whole models by primary key.
     *
     * @return ValueExpressionResult|null
     */
    private function analyzeRelationFilter(MethodCall|NullsafeMethodCall $call, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $result = ValueResult::unknown();

        $nullable = $call instanceof NullsafeMethodCall;
        $methodName = $call->name instanceof Identifier ? $call->name->toString() : null;

        if ($methodName === null) {
            return null; // @codeCoverageIgnore
        }

        /** @var PropertyFetch $varExpr */
        $varExpr = $call->var;
        $propName = $varExpr->name instanceof Identifier ? $varExpr->name->toString() : null;

        if ($propName === null) {
            return null; // @codeCoverageIgnore
        }

        $relationInfo = $this->resolveModelRelationTypeInfo($propName, $scope);

        if (str_ends_with($relationInfo['type'], '[]')) {
            return $this->manyRelationRead($call, $scope, $engine);
        }

        $modelFqcn = $relationInfo['modelFqcn'] ?? $this->resolveAccessorModelFqcn($propName, $scope);

        if ($modelFqcn === null) {
            // Try the multi-model accessor path (e.g. Attribute<ModelA|ModelB, never>).
            $modelFqcns = $this->resolveAccessorModelFqcns($propName, $scope);

            if ($modelFqcns === [] || ! array_all($modelFqcns, fn (string $fqcn): bool => $this->typesAsModelFilter($fqcn, $methodName))) {
                return null;
            }

            $keys = $this->extractFilterKeys($call, new ReflectionMethod(Model::class, $methodName));

            if ($keys === null || $keys === []) {
                return $this->attributeRecordResult($nullable);
            }

            $include = $methodName === 'only';

            /** @var list<string> $inlineTypes */
            $inlineTypes = [];
            /** @var list<class-string> $embeddedEnumFqcns */
            $embeddedEnumFqcns = [];
            /** @var list<class-string> $embeddedModelFqcns */
            $embeddedModelFqcns = [];
            /** @var TypesImportMap $embeddedCustomImports */
            $embeddedCustomImports = [];
            /** @var list<class-string<Model>> $seenFqcns */
            $seenFqcns = [];

            // Dedupe on the arm's own FQCN, not the rendered string: relationFilterModelReference()
            // renders class_basename($fqcn), so two different FQCNs sharing a basename (e.g. two
            // "User" models) would otherwise render identically and the second arm would be dropped.
            foreach ($modelFqcns as $fqcn) {
                if (in_array($fqcn, $seenFqcns, true)) {
                    continue;
                }

                $seenFqcns[] = $fqcn;
                $arm = $this->literalKeyFilterResult($fqcn, $keys, $include, $scope->carriesImports);

                if ($arm === null) {
                    continue;
                }

                $inlineTypes[] = $arm['type'];
                array_push($embeddedEnumFqcns, ...($arm['embeddedEnumFqcns'] ?? []));
                array_push($embeddedModelFqcns, ...(isset($arm['modelFqcn']) ? [$arm['modelFqcn']] : []), ...($arm['embeddedModelFqcns'] ?? []));

                foreach ($arm['customImports'] ?? [] as $path => $names) {
                    $embeddedCustomImports[$path] = [...($embeddedCustomImports[$path] ?? []), ...$names];
                }
            }

            if ($inlineTypes === []) {
                return null; // @codeCoverageIgnore
            }

            $inlineType = implode(' | ', $inlineTypes);

            if (! $scope->carriesImports && TsTypeString::shapeValueHasUnimportableToken($inlineType)) {
                return $this->attributeRecordResult($nullable);
            }

            if ($nullable) {
                $inlineType .= ' | null';
            }

            return [
                ...$result,
                // Neither channel is deduped: aliasPropertyType() walks each list positionally
                // against left-to-right occurrences of each basename in $inlineType, so a real
                // repeat — across arms or within one arm's own picked columns — must survive.
                'type' => $inlineType,
                'embeddedEnumFqcns' => $embeddedEnumFqcns,
                'embeddedModelFqcns' => $embeddedModelFqcns,
                'customImports' => $embeddedCustomImports,
            ];
        }

        if (! $this->typesAsModelFilter($modelFqcn, $methodName)) {
            return null;
        }

        $keys = $this->extractFilterKeys($call, new ReflectionMethod(Model::class, $methodName));

        if ($keys === null || $keys === []) {
            return $this->attributeRecordResult($nullable);
        }

        $filtered = $this->literalKeyFilterResult($modelFqcn, $keys, $methodName === 'only', $scope->carriesImports);

        if ($filtered === null) {
            return null;
        }

        if (! $scope->carriesImports && TsTypeString::shapeValueHasUnimportableToken($filtered['type'])) {
            return $this->attributeRecordResult($nullable);
        }

        if ($nullable) {
            $filtered['type'] .= ' | null';
        }

        return $filtered;
    }

    /**
     * The many-relation read a filter on it publishes, with `| null` through `?->`, or null when the read is untyped.
     *
     * `Eloquent\Collection::only()`/`except()` keep the models whose primary key is listed and return them whole, so
     * any key list leaves exactly the relation's own read, import channels included. A scope that cannot import the
     * element model keeps the list alone.
     *
     * @return ValueExpressionResult|null
     */
    private function manyRelationRead(MethodCall|NullsafeMethodCall $call, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $read = $engine->resolve($call->var);

        if (TsTypeString::isUnknownOnly($read['type'])) {
            return null;
        }

        if (! $scope->carriesImports && TsTypeString::shapeValueHasUnimportableToken($read['type'])) {
            $nullableRead = in_array('null', TsTypeString::splitTopLevelUnion($read['type']), true);
            $read = [...ValueResult::unknown(), 'type' => $nullableRead ? 'unknown[] | null' : 'unknown[]'];
        }

        if ($call instanceof NullsafeMethodCall && ! in_array('null', TsTypeString::splitTopLevelUnion($read['type']), true)) {
            $read['type'] .= ' | null';
        }

        return $read;
    }

    /**
     * Analyze `$var->map->only([...])` / `$var->map->except([...])` — Laravel's HigherOrderCollectionProxy
     * on `map`, which runs the filter method against every element and collects the results.
     *
     * @return ValueExpressionResult
     */
    private function analyzeMapProxyFilter(MethodCall|NullsafeMethodCall $call, AnalysisScope $scope): array
    {
        $result = ValueResult::unknown();

        $methodName = $call->name instanceof Identifier ? $call->name->toString() : null;

        if ($methodName === null) {
            return $result; // @codeCoverageIgnore
        }

        /** @var PropertyFetch $mapFetch */
        $mapFetch = $call->var;
        $elementModel = $this->resolveMapProxyElementModel($mapFetch->var, $scope);

        if ($elementModel === null || ! $this->typesAsModelFilter($elementModel, $methodName)) {
            return $result;
        }

        $keys = $this->extractFilterKeys($call, new ReflectionMethod(Model::class, $methodName));

        if ($keys === null || $keys === []) {
            return $result;
        }

        $filterResult = $this->resolveFilteredRelationType($elementModel, $keys, $methodName === 'only');

        if ($filterResult['type'] === 'unknown') {
            return $result;
        }

        $nullable = $call instanceof NullsafeMethodCall;

        if (! $scope->carriesImports && TsTypeString::shapeValueHasUnimportableToken($filterResult['type'])) {
            return [...$result, 'type' => $nullable ? 'Record<string, unknown>[] | null' : 'Record<string, unknown>[]'];
        }

        $inlineType = ValueResult::arrayWrapType($filterResult['type']);

        if ($nullable) {
            $inlineType .= ' | null';
        }

        return [
            ...$result,
            'type' => $inlineType,
            'embeddedEnumFqcns' => $filterResult['enumFqcns'],
            'embeddedModelFqcns' => $filterResult['modelFqcns'],
            'customImports' => $filterResult['customImports'],
        ];
    }

    /**
     * Whether the filter answers type a model's only()/except(), asked of the receiver rules so both owners agree.
     *
     * @param  class-string  $modelFqcn
     */
    private function typesAsModelFilter(string $modelFqcn, string $methodName): bool
    {
        return resolve(ReceiverMethodReturnResolver::class)->typesAsModelFilter($modelFqcn, $methodName);
    }

    /**
     * If $propName is an accessor attribute whose getter returns exactly one Eloquent Model
     * subclass, return its FQCN. Used as a fallback when the property is not a database relation.
     * The sole implementation — the analyzer-side copy was deleted as dead code, not moved here.
     *
     * @return class-string<Model>|null
     */
    private function resolveAccessorModelFqcn(string $propName, AnalysisScope $scope): ?string
    {
        if ($scope->modelClass === null) {
            return null; // @codeCoverageIgnore
        }

        return resolve(ModelAttributeResolver::class)->resolveAccessorModelFqcn($scope->modelClass, $propName);
    }

    /**
     * Return all Eloquent Model FQCNs that an accessor returns, used when the accessor union-types
     * multiple models. The sole implementation — the analyzer-side copy was deleted as dead code,
     * not moved here, same as resolveAccessorModelFqcn() above.
     *
     * @return list<class-string<Model>>
     */
    private function resolveAccessorModelFqcns(string $propName, AnalysisScope $scope): array
    {
        if ($scope->modelClass === null) {
            return []; // @codeCoverageIgnore
        }

        return resolve(ModelAttributeResolver::class)->resolveAccessorModelFqcns($scope->modelClass, $propName);
    }
}
