<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\FiltersAttributeKeys;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesFilteredRelationTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesMapProxyElementModels;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesModelRelationTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use ReflectionMethod;

/**
 * `$this->relation->only([...])`/`->except([...])` and Laravel's `map` HigherOrderCollectionProxy
 * filter (`$var->map->only([...])`/`->except([...])`) — relation/collection attribute filters.
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
        // $this->relation->only([...]) or $this->relation?->only([...])
        if (($expr instanceof MethodCall || $expr instanceof NullsafeMethodCall)
            && $expr->name instanceof Identifier
            && in_array($expr->name->toString(), $this->supportedAttributeFilters(), true)
            && $expr->var instanceof PropertyFetch
            && $expr->var->var instanceof Variable
            && $expr->var->var->name === 'this'
        ) {
            return $this->analyzeRelationFilter($expr, $scope);
        }

        // $var->map->only([...]) / ->except([...]) — Laravel's HigherOrderCollectionProxy on `map`:
        // call the filter method on every element and collect the results. The PropertyFetch here is
        // literally named 'map' (the proxy), never 'this' — disjoint from the relation guard above.
        if (($expr instanceof MethodCall || $expr instanceof NullsafeMethodCall)
            && $expr->name instanceof Identifier
            && in_array($expr->name->toString(), $this->supportedAttributeFilters(), true)
            && $expr->var instanceof PropertyFetch
            && $expr->var->name instanceof Identifier
            && $expr->var->name->toString() === 'map'
        ) {
            return $this->analyzeMapProxyFilter($expr, $scope);
        }

        return null;
    }

    /**
     * Analyze `$this->relation->only([...])` or `$this->relation?->only([...])`.
     *
     * @return ValueExpressionResult
     */
    private function analyzeRelationFilter(MethodCall|NullsafeMethodCall $call, AnalysisScope $scope): array
    {
        $result = ValueResult::unknown();

        $nullable = $call instanceof NullsafeMethodCall;
        $methodName = $call->name instanceof Identifier ? $call->name->toString() : null;

        if ($methodName === null) {
            return $result; // @codeCoverageIgnore
        }

        /** @var PropertyFetch $varExpr */
        $varExpr = $call->var;
        $propName = $varExpr->name instanceof Identifier ? $varExpr->name->toString() : null;

        if ($propName === null) {
            return $result; // @codeCoverageIgnore
        }

        $relationInfo = $this->resolveModelRelationTypeInfo($propName, $scope);
        $modelFqcn = $relationInfo['modelFqcn'] ?? $this->resolveAccessorModelFqcn($propName, $scope);

        if ($modelFqcn === null) {
            // Try the multi-model accessor path (e.g. Attribute<ModelA|ModelB, never>).
            $modelFqcns = $this->resolveAccessorModelFqcns($propName, $scope);

            if ($modelFqcns === []) {
                return $result; // @codeCoverageIgnore
            }

            $keys = $this->extractFilterKeys($call, new ReflectionMethod(Model::class, $methodName));

            if ($keys === null || $keys === []) {
                return $result; // @codeCoverageIgnore
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

                // Every filter key is a plain DB column: reference the arm's own model interface so
                // its #[TsCasts]/@property refinements stay authoritative, same as the single-model path.
                $modelReference = $this->relationFilterModelReference($fqcn, $keys, $include);

                if ($modelReference !== null) {
                    $inlineTypes[] = $modelReference;
                    $embeddedModelFqcns[] = $fqcn;

                    continue;
                }

                $filterResult = $this->resolveFilteredRelationType($fqcn, $keys, $include);

                if ($filterResult['type'] === 'unknown') {
                    continue;
                }

                $inlineTypes[] = $filterResult['type'];
                array_push($embeddedEnumFqcns, ...$filterResult['enumFqcns']);
                array_push($embeddedModelFqcns, ...$filterResult['modelFqcns']);

                foreach ($filterResult['customImports'] as $path => $names) {
                    $embeddedCustomImports[$path] = [...($embeddedCustomImports[$path] ?? []), ...$names];
                }
            }

            if ($inlineTypes === []) {
                return $result; // @codeCoverageIgnore
            }

            $inlineType = implode(' | ', $inlineTypes);

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

        $receiver = str_ends_with($relationInfo['type'], '[]') ? EloquentCollection::class : Model::class;
        $keys = $this->extractFilterKeys($call, new ReflectionMethod($receiver, $methodName));

        if ($keys === null || $keys === []) {
            return $result; // @codeCoverageIgnore
        }

        $include = $methodName === 'only';

        // Every filter key is a plain DB column: reference the emitted model interface directly so its
        // #[TsCasts]/@property refinements stay authoritative instead of being re-derived and lost.
        $modelReference = $this->relationFilterModelReference($modelFqcn, $keys, $include);

        if ($modelReference !== null) {
            $type = $modelReference;

            if (str_ends_with($relationInfo['type'], '[]')) {
                $type .= '[]';
            }

            if ($nullable) {
                $type .= ' | null';
            }

            return [
                ...$result,
                'type' => $type,
                'modelFqcn' => $modelFqcn,
            ];
        }

        $filterResult = $this->resolveFilteredRelationType($modelFqcn, $keys, $include);
        $inlineType = $filterResult['type'];

        // Wrap in array suffix when the relation is a *-many type (HasMany, BelongsToMany, etc.)
        if (str_ends_with($relationInfo['type'], '[]') && $inlineType !== 'unknown') {
            $inlineType .= '[]';
        }

        if ($nullable && $inlineType !== 'unknown') {
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

        if ($elementModel === null) {
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

        $inlineType = ValueResult::arrayWrapType($filterResult['type']);

        if ($call instanceof NullsafeMethodCall) {
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
     * Build a Pick<Model, …> reference when every filter key is a declared model column.
     *
     * Targets the bare model interface: except() iterates only $this->getAttributes(), so relations and
     * accessors never surface. Picks the complement, not Omit<>, to stay independent of the active template.
     *
     * @param  class-string<Model>  $modelFqcn
     * @param  list<string>  $keys
     */
    private function relationFilterModelReference(string $modelFqcn, array $keys, bool $include): ?string
    {
        $resolver = resolve(ModelAttributeResolver::class);
        $columns = $resolver->publishedColumnNames($modelFqcn);

        if ($columns === []) {
            return null; // @codeCoverageIgnore
        }

        foreach ($keys as $key) {
            if (! in_array($key, $columns, true)) {
                return null;
            }
        }

        $picked = $include ? $keys : array_values(array_diff($columns, $keys));

        if ($picked === []) {
            return 'Pick<'.class_basename($modelFqcn).', never>';
        }

        $quoted = implode(' | ', array_map(fn (string $k): string => "'".$k."'", $picked));

        return 'Pick<'.class_basename($modelFqcn).', '.$quoted.'>';
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
