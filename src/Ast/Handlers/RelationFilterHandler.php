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
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverType;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Casts\AsEncryptedCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Identifier;
use ReflectionMethod;

/**
 * `$this->relation->only([...])`/`->except([...])`, also read through `$this->resource`, and Laravel's `map`
 * HigherOrderCollectionProxy filter (`$var->map->only([...])`). The relation arm declines what it cannot type, so a
 * later handler answers; docs/components/resource-ast-analyzer.md § Attribute filters has each arm's rules.
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

        // A collection's filter never runs on its elements, so its class decides before any element model it names.
        $collectionClass = $relationInfo['modelFqcn'] === null ? $this->memberCollectionClass($propName, $scope) : null;

        if ($collectionClass !== null) {
            return $this->analyzeCollectionMemberFilter($call, $methodName, $propName, $collectionClass, $scope, $engine);
        }

        $modelFqcn = $relationInfo['modelFqcn'] ?? $this->resolveAccessorModelFqcn($propName, $scope);

        if ($modelFqcn === null) {
            // Try the multi-model accessor path (e.g. Attribute<ModelA|ModelB, never>).
            return $this->analyzeMultiModelFilter($call, $methodName, $this->resolveAccessorModelFqcns($propName, $scope), $scope);
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

        if ($nullable) {
            $filtered['type'] .= ' | null';
        }

        return $filtered;
    }

    /**
     * Analyze a filter on a member holding a collection, declining for a class whose filter is its own.
     * Support\Collection's filter keeps the listed keys. Eloquent\Collection's keeps whole models by primary key, so an
     * accessor holding one publishes a list of its models, else `unknown[]`; a cast building one has no key to match.
     *
     * @param  class-string  $collectionClass
     * @return ValueExpressionResult|null
     */
    private function analyzeCollectionMemberFilter(
        MethodCall|NullsafeMethodCall $call,
        string $methodName,
        string $propName,
        string $collectionClass,
        AnalysisScope $scope,
        ExpressionEngine $engine,
    ): ?array {
        if ($this->runsCollectionFilter($collectionClass, $methodName)) {
            return $this->attributeRecordResult($call instanceof NullsafeMethodCall);
        }

        if (! $this->runsEloquentCollectionFilter($collectionClass, $methodName) || ! $this->isAccessorMember($propName, $scope)) {
            return null;
        }

        $models = $this->resolveAccessorModelFqcns($propName, $scope);
        $nullable = $call instanceof NullsafeMethodCall || in_array('null', TsTypeString::splitTopLevelUnion($engine->resolve($call->var)['type']), true);
        $suffix = $nullable ? ' | null' : '';

        if ($models === [] || ! $scope->carriesImports) {
            return [...ValueResult::unknown(), 'type' => 'unknown[]'.$suffix];
        }

        $element = implode(' | ', array_map(class_basename(...), $models));

        return [
            ...ValueResult::unknown(),
            'type' => ValueResult::arrayWrapType($element).$suffix,
            ...(count($models) === 1 ? ['modelFqcn' => $models[0]] : ['embeddedModelFqcns' => $models]),
        ];
    }

    /**
     * Analyze a filter on an accessor typed as a union of models, one arm per model, declining when an arm is untyped.
     *
     * An arm whose model overrides the filter with a return reflection types publishes that return. The attribute
     * arms become one `Record<string, unknown>` for a runtime key list.
     *
     * @param  list<class-string<Model>>  $modelFqcns
     * @return ValueExpressionResult|null
     */
    private function analyzeMultiModelFilter(MethodCall|NullsafeMethodCall $call, string $methodName, array $modelFqcns, AnalysisScope $scope): ?array
    {
        if ($modelFqcns === []) {
            return null;
        }

        $nullable = $call instanceof NullsafeMethodCall;
        $keys = $this->extractFilterKeys($call, new ReflectionMethod(Model::class, $methodName));
        $runtimeKeys = $keys === null || $keys === [];

        /** @var list<array{attributes: bool, result: ValueExpressionResult}> $arms */
        $arms = [];
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

            if (! $this->typesAsModelFilter($fqcn, $methodName)) {
                $override = resolve(ReceiverMethodReturnResolver::class)->resolve(ReceiverType::of($fqcn), $methodName, $scope, call: $call);

                // An `unknown` arm leaves the whole union unknown.
                if ($override === null || TsTypeString::isUnknownOnly($override['type'])) {
                    return null;
                }

                $arms[] = ['attributes' => false, 'result' => $override];

                continue;
            }

            $arm = $runtimeKeys ? $this->attributeRecordResult(false) : $this->literalKeyFilterResult($fqcn, $keys, $methodName === 'only', $scope->carriesImports);

            if ($arm !== null) {
                $arms[] = ['attributes' => true, 'result' => $arm];
            }
        }

        if ($runtimeKeys && array_all($arms, fn (array $arm): bool => $arm['attributes'])) {
            return $this->attributeRecordResult($nullable);
        }

        return $arms === [] ? null : $this->unionArms($arms, $nullable);
    }

    /**
     * Join the arms into one union with every arm's import channels, hoisting a `null` any arm or `?->` adds.
     *
     * @param  non-empty-list<array{attributes: bool, result: ValueExpressionResult}>  $arms
     * @return ValueExpressionResult
     */
    private function unionArms(array $arms, bool $nullable): array
    {
        /** @var list<string> $inlineTypes */
        $inlineTypes = [];
        /** @var list<class-string> $embeddedEnumFqcns */
        $embeddedEnumFqcns = [];
        /** @var list<class-string> $embeddedModelFqcns */
        $embeddedModelFqcns = [];
        /** @var TypesImportMap $embeddedCustomImports */
        $embeddedCustomImports = [];

        foreach ($arms as ['result' => $arm]) {
            $nullable = $nullable || in_array('null', TsTypeString::splitTopLevelUnion($arm['type']), true);
            $type = ValueResult::stripNullArm($arm['type']);

            // A record carries no import channel, so a repeat of one adds nothing.
            if ($type === 'Record<string, unknown>' && in_array($type, $inlineTypes, true)) {
                continue;
            }

            $inlineTypes[] = $type;
            array_push($embeddedEnumFqcns, ...(isset($arm['directEnumFqcn']) ? [$arm['directEnumFqcn']] : []), ...($arm['embeddedEnumFqcns'] ?? []));
            array_push($embeddedModelFqcns, ...(isset($arm['modelFqcn']) ? [$arm['modelFqcn']] : []), ...($arm['embeddedModelFqcns'] ?? []));

            foreach ($arm['customImports'] ?? [] as $path => $names) {
                $embeddedCustomImports[$path] = [...($embeddedCustomImports[$path] ?? []), ...$names];
            }
        }

        return [
            ...ValueResult::unknown(),
            // Neither channel is deduped: aliasPropertyType() walks each list positionally
            // against left-to-right occurrences of each basename in the type, so a real
            // repeat — across arms or within one arm's own picked columns — must survive.
            'type' => implode(' | ', $inlineTypes).($nullable ? ' | null' : ''),
            'embeddedEnumFqcns' => $embeddedEnumFqcns,
            'embeddedModelFqcns' => $embeddedModelFqcns,
            'customImports' => $embeddedCustomImports,
        ];
    }

    /**
     * The many-relation read a filter on it publishes, with `| null` through `?->`, or null when the read is untyped.
     * `Eloquent\Collection::only()`/`except()` keep the listed models whole, so any key list leaves the relation's own
     * read, channels included; a scope that cannot import the element model keeps the list alone.
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
        $nullable = $call instanceof NullsafeMethodCall;

        if ($elementModel === null) {
            return $result;
        }

        if (! $this->typesAsModelFilter($elementModel, $methodName)) {
            $override = resolve(ReceiverMethodReturnResolver::class)->resolve(ReceiverType::of($elementModel), $methodName, $scope, call: $call);

            return $override === null
                ? $result
                : [...$override, 'type' => ValueResult::arrayWrapType($override['type']).($nullable ? ' | null' : '')];
        }

        $keys = $this->extractFilterKeys($call, new ReflectionMethod(Model::class, $methodName));

        if ($keys === null || $keys === []) {
            return $result;
        }

        $filterResult = $this->resolveFilteredRelationType($elementModel, $keys, $methodName === 'only', tokenMembersUnknown: ! $scope->carriesImports);

        if ($filterResult['type'] === 'unknown') {
            return $result;
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
     * The collection class a model member holds: what its accessor or cast reflects, else what a Laravel collection
     * cast builds, which is Support\Collection or `using()`'s class. Those casts declare no return type to reflect.
     *
     * @return class-string|null null when the member holds no collection
     */
    private function memberCollectionClass(string $propName, AnalysisScope $scope): ?string
    {
        if ($scope->modelClass === null) {
            return null; // @codeCoverageIgnore
        }

        $resolver = resolve(ModelAttributeResolver::class);
        $class = $resolver->resolveAttributeClass($scope->modelClass, $propName);

        if ($class !== null) {
            return is_a($class, Collection::class, true) ? $class : null;
        }

        $cast = (string) ($resolver->getAttributes($scope->modelClass)?->firstWhere('name', $propName)['cast'] ?? '');

        if ($cast === 'collection' || $cast === 'encrypted:collection') {
            return Collection::class;
        }

        $head = Str::before($cast, ':');

        if (! is_a($head, AsCollection::class, true) && ! is_a($head, AsEncryptedCollection::class, true)) {
            return null;
        }

        $collection = str_contains($cast, ':') ? Str::before(Str::after($cast, ':'), ',') : '';

        return is_a($collection, Collection::class, true) ? $collection : Collection::class;
    }

    /**
     * Whether a model member is an accessor, new-style or old-style, rather than a column or a cast.
     */
    private function isAccessorMember(string $propName, AnalysisScope $scope): bool
    {
        if ($scope->modelClass === null) {
            return false; // @codeCoverageIgnore
        }

        $cast = resolve(ModelAttributeResolver::class)->getAttributes($scope->modelClass)?->firstWhere('name', $propName)['cast'] ?? null;

        return $cast === 'attribute' || $cast === 'accessor';
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
