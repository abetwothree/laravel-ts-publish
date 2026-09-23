<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\AnalyzesPluckCalls;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\FiltersAttributeKeys;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesMapProxyElementModels;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesModelRelationTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesRelatedModelTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\SpellsKeyedCollections;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\DeclaredTypeWeigher;
use AbeTwoThree\LaravelTsPublish\Ast\PropertyDocblockTypeReader;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverType;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Enumerable;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use ReflectionMethod;

/**
 * Expressions rooted at a bound variable rather than `$this` — `$item->name`, `$items->map(…)`,
 * `$items->pluck('x')`, `$item->method()`, and the bare variable itself resolved through an inline `@var` on its
 * assignment, then the scope's model, collection, closure-parameter and local-assignment binding maps.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class VariableHandler implements ExpressionHandler
{
    use AnalyzesPluckCalls;
    use FiltersAttributeKeys;
    use InspectsAstNodes;
    use ResolvesMapProxyElementModels;
    use ResolvesModelRelationTypes;
    use ResolvesRelatedModelTypes;
    use SpellsKeyedCollections;

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [PropertyFetch::class, MethodCall::class, Variable::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        // A trailing argument-less values()/all() on a collection takes its element type from the receiver chain, which
        // is the only thing that knows it: all() hands the array back, keys and all, and values() re-indexes it into a
        // list. On any other class the method is its own, so the receiver's type says nothing about what it returns.
        if ($expr instanceof MethodCall
            && $expr->var instanceof MethodCall
            && $expr->name instanceof Identifier
            && in_array($expr->name->toString(), ['values', 'all'], true)
            && ! $expr->isFirstClassCallable()
            && CallArguments::for($expr, new ReflectionMethod(EloquentCollection::class, $expr->name->toString()))->isEmpty()
            && $this->holdsOnlyCollections($expr->var, $scope)
        ) {
            $receiverResult = $engine->resolve($expr->var);
            $type = $expr->name->toString() === 'values' ? $this->valuesList($receiverResult['type']) : $receiverResult['type'];

            if ($type !== null && $type !== 'unknown') {
                return [...$receiverResult, 'type' => $type];
            }
        }

        // $variable->property — resolve against the variable's own bound model (whenLoaded param,
        // map param, foreach value var), falling back to the ambient whenLoaded closure model.
        if ($expr instanceof PropertyFetch
            && $expr->var instanceof Variable
            && is_string($expr->var->name)
            && $expr->var->name !== 'this'
            && $expr->name instanceof Identifier
        ) {
            $boundModel = $scope->varModelBindings[$expr->var->name] ?? $this->ambientModel($expr->var, $scope);

            if ($boundModel !== null) {
                return $this->analyzeRelatedModelProperty($expr->name->toString(), $scope, $boundModel);
            }
        }

        // `$variable->map(fn (Item $item) => [...])` — no ambient closureRelationModelClass is required: the element
        // model comes from the param's type hint, or from the receiver's own to-many whenLoaded binding.
        if ($expr instanceof MethodCall && $this->mappedVariable($expr) !== null) {
            $mapResult = $this->analyzeVariableMapCall($expr, $scope, $engine);

            if ($mapResult !== null) {
                return $mapResult;
            }
        }

        // $variable->pluck('field') — resolve to an array of the field's type
        if ($scope->closureRelationModelClass !== null
            && $expr instanceof MethodCall
            && $expr->var instanceof Variable
            && is_string($expr->var->name)
            && $expr->var->name !== 'this'
            && $expr->name instanceof Identifier
            && $expr->name->toString() === 'pluck'
        ) {
            return $this->analyzeVariablePluckCall($expr, $scope);
        }

        // $variable->method() — resolve against the variable's own bound model, falling back to the
        // ambient whenLoaded closure model. An only()/except() filter is skipped: the receiver rules own it, and
        // reflecting it here reads Model::except()'s `@return array` as a list or filters a collection by key.
        if ($expr instanceof MethodCall
            && $expr->var instanceof Variable
            && is_string($expr->var->name)
            && $expr->var->name !== 'this'
            && $expr->name instanceof Identifier
            && ! $this->callsAttributeFilter($expr)
        ) {
            $boundModel = $scope->varModelBindings[$expr->var->name] ?? $this->ambientModel($expr->var, $scope);

            if ($boundModel !== null) {
                return $this->analyzeRelatedModelMethodCall($expr->name->toString(), $scope, $boundModel);
            }
        }

        if (! $expr instanceof Variable || ! is_string($expr->name)) {
            return null;
        }

        $bound = $this->boundValue($expr->name, $scope, $engine);
        $declared = $this->declaredValue($expr, $scope);

        return $declared === null ? $bound : resolve(DeclaredTypeWeigher::class)->value($declared, $bound);
    }

    /**
     * A bare variable's value from its model, collection, value, closure-parameter or local-assignment binding.
     *
     * @return ValueExpressionResult|null
     */
    private function boundValue(string $name, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        // Bound to a model class (whenLoaded param, map param, foreach value var) — resolves to the model's own type.
        // Checked before closure-param/local-var expression bindings, which resolve through a *different* expression.
        if (isset($scope->varModelBindings[$name])) {
            $modelFqcn = $scope->varModelBindings[$name];

            return [
                ...ValueResult::unknown(),
                'type' => class_basename($modelFqcn),
                'optional' => false,
                'modelFqcn' => $modelFqcn,
            ];
        }

        // Bound to a whole relation collection (to-many whenLoaded param) — resolves to the collection type, e.g.
        // `User[]`, never the singular element model.
        if (isset($scope->varCollectionBindings[$name])) {
            $binding = $scope->varCollectionBindings[$name];

            return [
                ...ValueResult::unknown(),
                'type' => $binding['type'],
                'optional' => false,
                'modelFqcn' => $binding['modelFqcn'],
            ];
        }

        // Bound to an already-resolved value — a `collect(...)->map()` closure param, whose element type
        // CollectionPipelineHandler resolved before descending into the body.
        if (isset($scope->varValueBindings[$name])) {
            return $scope->varValueBindings[$name];
        }

        // Bound to a closure parameter's expression or a top-level local assignment; the closure param is the narrower
        // scope, and the re-entrancy guard makes a cyclic binding resolve as unknown.
        $boundExpr = $scope->closureParamExprBindings[$name] ?? $scope->localVarBindings[$name] ?? null;

        if ($boundExpr === null || isset($scope->resolvingLocalVars[$name])) {
            return null;
        }

        $scope->resolvingLocalVars[$name] = true;

        try {
            return $engine->resolve($boundExpr);
        } finally {
            unset($scope->resolvingLocalVars[$name]);
        }
    }

    /**
     * The type an inline `@var` on a variable's assignment declares for this read, when precise enough to publish.
     *
     * A vague declaration yields to the assignment's own reading, as a vague declared return yields to the method body.
     *
     * @return ValueExpressionResult|null
     */
    private function declaredValue(Variable $variable, AnalysisScope $scope): ?array
    {
        $declared = $scope->declaredAt($variable);
        $value = $declared === null
            ? null
            : resolve(PropertyDocblockTypeReader::class)->readDeclared($declared['type'], $declared['context']);

        return $value !== null && ! TsTypeString::isVagueTsType($value['type']) && ValueResult::namesOnlyPublishedModels($value)
            ? $value
            : null;
    }

    /**
     * The model a whenLoaded closure's relation holds, as a guess at an unbound variable; none when an inline `@var`
     * declares classes it is not one of, since the receiver path then types the read from that declaration.
     *
     * @return class-string<Model>|null
     */
    private function ambientModel(Variable $variable, AnalysisScope $scope): ?string
    {
        $ambient = $scope->closureRelationModelClass;
        $declared = resolve(ReceiverClassResolver::class)->declaredClasses($variable, $scope);

        return $ambient !== null && ($declared === null || ReceiverType::of($ambient)->within($declared)) ? $ambient : null;
    }

    /**
     * Analyze `$variable->map(fn (Item $item) => [...])` with the first param bound to its element model — the type
     * hint, else the receiver's to-many whenLoaded element — and wrap the body result as `elementType[]`.
     *
     * Returns null when neither names a model, or the first param is variadic, deferring to the generic method handler.
     *
     * @return ValueExpressionResult|null
     */
    private function analyzeVariableMapCall(MethodCall $call, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $closureArg = $this->mapArguments($call)->named('callback')?->value;

        if ($closureArg === null) {
            return null;
        }

        if ($closureArg instanceof ArrowFunction) {
            $params = $closureArg->params;
        } elseif ($closureArg instanceof ClosureExpr) {
            $params = $closureArg->params;
        } else {
            return null;
        }

        if ($params === []) {
            return null;
        }

        $firstParam = $params[0];

        // map() passes ($value, $key), so a variadic first param collects both and never holds one element.
        if ($firstParam->variadic) {
            return null;
        }

        // A named class type hint (already FQCN-resolved by NameResolver) wins when present — it's
        // the more specific signal. Otherwise fall back to the receiver's own relation binding, the
        // same one ConditionalMethodHandler::analyzeWhenLoaded() already populated for a to-many param.
        $paramClass = $firstParam->type instanceof Name
            ? $firstParam->type->toString()
            : $this->resolveMapProxyElementModel($call->var, $scope);

        if ($paramClass === null || ! class_exists($paramClass) || ! is_a($paramClass, Model::class, true)) {
            return null;
        }

        /** @var class-string<Model> $paramClass */
        $previousRelationModel = $scope->closureRelationModelClass;
        $previousNameBindings = $scope->nameBindings();

        try {
            $scope->closureRelationModelClass = $paramClass;
            $scope->claimParameters($closureArg);

            // ReceiverClassResolver reads a parameter from varModelBindings, never closureRelationModelClass.
            if ($firstParam->var instanceof Variable && is_string($firstParam->var->name)) {
                $scope->varModelBindings[$firstParam->var->name] = $paramClass;
            }

            $returnExprs = $this->resolveClosureReturnExpressions($closureArg);

            $bodyResult = match (count($returnExprs)) {
                0 => null,
                1 => $engine->resolve($returnExprs[0]),
                default => ValueResult::analyzeClosureUnion($returnExprs, $engine, $scope),
            };
        } finally {
            $scope->closureRelationModelClass = $previousRelationModel;
            $scope->restoreNameBindings($previousNameBindings);
        }

        if ($bodyResult === null || $bodyResult['type'] === 'unknown') {
            return null;
        }

        // ValueResult::arrayWrapType(), not a raw '[]' suffix: a union body (e.g. a mixed AsEnum/direct-enum
        // ternary) must be parenthesized before the array suffix binds.
        $bodyResult['type'] = ValueResult::arrayWrapType($bodyResult['type']);
        $bodyResult['optional'] = false;

        return $bodyResult;
    }

    /**
     * A `$variable->map(...)` call's arguments mapped against Collection::map(callable $callback).
     */
    private function mapArguments(MethodCall $call): CallArguments
    {
        return CallArguments::for($call, new ReflectionMethod(EloquentCollection::class, 'map'));
    }

    /**
     * Whether an expression holds only Illuminate collections, whose values() and all() the engine knows.
     */
    private function holdsOnlyCollections(Expr $expr, AnalysisScope $scope): bool
    {
        $receiver = resolve(ReceiverClassResolver::class)->resolve($expr, $scope);

        // An unnamed receiver passes only as the map arm's `$variable->map(callback)` over an unnamed variable,
        // which that arm already types as a Collection map; values() leaves it one.
        if ($receiver === null) {
            while ($expr instanceof MethodCall
                && $expr->name instanceof Identifier
                && $expr->name->toString() === 'values'
                && ! $expr->isFirstClassCallable()
                && $expr->getArgs() === []
            ) {
                $expr = $expr->var;
            }

            $mapped = $this->mappedVariable($expr);

            return $mapped !== null && resolve(ReceiverClassResolver::class)->resolve($mapped, $scope) === null;
        }

        return array_all($receiver->classes, fn (string $class): bool => is_a($class, Enumerable::class, true));
    }

    /**
     * The variable a `$variable->map(callback)` call maps over, else null.
     */
    private function mappedVariable(Expr $expr): ?Variable
    {
        return $expr instanceof MethodCall
            && $expr->var instanceof Variable
            && is_string($expr->var->name)
            && $expr->var->name !== 'this'
            && $expr->name instanceof Identifier
            && $expr->name->toString() === 'map'
            && ! $this->mapArguments($expr)->isEmpty()
                ? $expr->var
                : null;
    }
}
