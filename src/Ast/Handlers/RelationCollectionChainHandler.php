<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\CallMatcher;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\AnalyzesPluckCalls;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\FiltersAttributeKeys;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsResourceSubject;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesModelRelationTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesRelatedModelTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\SpellsKeyedCollections;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverMethodReturnResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ReflectedTypeAcceptor;
use AbeTwoThree\LaravelTsPublish\Ast\SubjectHelperReturnResolver;
use AbeTwoThree\LaravelTsPublish\Ast\SubjectMethodTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\Int_;
use ReflectionClass;
use ReflectionMethod;

/**
 * Non-nullsafe method calls on `$this`: collection chains rooted at a many-relation, a call through the
 * `$this->resource` proxy, on a date-cast column or on a model-less subject's own property, and the bare `$this->m()`.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class RelationCollectionChainHandler implements ExpressionHandler
{
    use AnalyzesPluckCalls;
    use FiltersAttributeKeys;
    use InspectsAstNodes;
    use InspectsResourceSubject;
    use ResolvesModelRelationTypes;
    use ResolvesRelatedModelTypes;
    use SpellsKeyedCollections;

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [MethodCall::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        // On a model-backed scope a filter is left to RelationFilterHandler or the receiver rules, even where neither
        // answers: reflection reads except()'s `@return array` as a list, and a many-relation keeps whole models.
        if ($expr instanceof MethodCall && $scope->modelClass !== null && $this->callsAttributeFilter($expr)) {
            return null;
        }

        // Collection chains rooted at `$this->{manyRelation}` (e.g. `->take(5)->map(...)->values()`).
        if ($expr instanceof MethodCall) {
            $chainResult = $this->analyzeRelationCollectionChain($expr, $scope, $engine);

            if ($chainResult !== null) {
                return $chainResult;
            }
        }

        // `$this->resource->m()` runs what a bare `$this->m()` forwards to, so both publish alike, a vague answer too.
        if ($expr instanceof MethodCall && $this->isResourceFetch($expr->var) && $expr->name instanceof Identifier) {
            return $this->forwardedMethodReturn($expr->name->toString(), $scope);
        }

        // Any other `$this->prop->m()` types from the class the property holds, never the scope's model: here only on a
        // date-cast column or a model-less subject's own property, and otherwise in ReceiverMethodCallHandler.
        if ($expr instanceof MethodCall
            && $expr->var instanceof PropertyFetch
            && $this->isThisPropertyFetch($expr->var)
            && $expr->var->name instanceof Identifier
            && $expr->name instanceof Identifier
        ) {
            return $this->heldPropertyMethodReturn($expr->var->name->toString(), $expr->name->toString(), $scope)
                ?? $this->subjectPropertyMethodReturn($expr, $expr->name->toString(), $scope);
        }

        // Generic `$this->method()` — reflect the declared return type, else read a helper that wraps an enum; the
        // helper guards above ran first.
        if ($expr instanceof MethodCall
            && $expr->var instanceof Variable
            && $expr->var->name === 'this'
            && $expr->name instanceof Identifier
        ) {
            return resolve(SubjectMethodTypeResolver::class)->resolve($scope, $expr->name->toString())
                ?? resolve(SubjectHelperReturnResolver::class)->resolveEnumResourceReturn($expr, $scope, $engine);
        }

        return null;
    }

    /**
     * Analyze a method-call chain on `$this->{manyRelation}` of identity-preserving ops plus at most
     * one `map()`/`pluck()`, or an argless `first()`/`last()`.
     *
     * Anything else returns null and falls through — e.g. `$this->items->count()` reaches the count() rule.
     *
     * @return ValueExpressionResult|null
     */
    private function analyzeRelationCollectionChain(MethodCall $call, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $identityOps = [
            'take', 'skip', 'filter', 'reject', 'values', 'unique',
            'sortBy', 'sortByDesc', 'slice', 'reverse', 'where', 'whereNotNull',
            'load', 'loadMissing', 'all',
        ];

        // Walk down the chain collecting op names until we reach $this->prop.
        /** @var list<array{name: string, node: MethodCall}> $ops */
        $ops = [];
        $node = $call;

        while ($node instanceof MethodCall) {
            if (! $node->name instanceof Identifier) {
                return null;
            }

            $ops[] = ['name' => $node->name->toString(), 'node' => $node];
            $node = $node->var;
        }

        if (! $node instanceof PropertyFetch || ! $this->isThisPropertyFetch($node) || ! $node->name instanceof Identifier) {
            return null;
        }

        $relationInfo = $this->resolveModelRelationTypeInfo($node->name->toString(), $scope);

        if (! str_ends_with($relationInfo['type'], '[]') || $relationInfo['modelFqcn'] === null) {
            return null;
        }

        $elementModel = $relationInfo['modelFqcn'];

        // first()/last() as the outermost op terminate the chain with a single element or null. $ops[0]
        // is the outermost call because the walk above collects outside-in, and always exists here: the
        // while loop above ran at least once, since $call is typed as MethodCall.
        $terminalOp = $ops[0]['name'];
        $isTerminal = ($terminalOp === 'first' || $terminalOp === 'last')
            && ! $ops[0]['node']->isFirstClassCallable()
            && $this->collectionArguments($ops[0]['node'], $terminalOp)->isEmpty();

        if ($isTerminal) {
            array_shift($ops);
        }

        $mapNode = null;
        $pluckNode = null;

        // A relation collection starts keyed 0..n-1; each op below says whether that still holds.
        $sequentialKeys = true;

        foreach (array_reverse($ops) as $op) {
            if (in_array($op['name'], $identityOps, true)) {
                $sequentialKeys = match ($op['name']) {
                    'values' => true,
                    'take' => $sequentialKeys && $this->isFrontAnchoredTake($op['node']),
                    'load', 'loadMissing', 'all' => $sequentialKeys,
                    default => false,
                };

                continue;
            }

            if ($op['name'] === 'map' && $mapNode === null && $pluckNode === null) {
                // map() preserves the receiver's keys, so it neither breaks nor restores sequentiality.
                $mapNode = $op['node'];

                continue;
            }

            if ($op['name'] === 'pluck' && $pluckNode === null && $mapNode === null) {
                $pluckNode = $op['node'];
                $sequentialKeys = $this->collectionArguments($op['node'], 'pluck')->named('key') === null;

                continue;
            }

            // concat() is identity only when it appends the very same collection type; a different
            // element type makes a genuinely different collection, so that declines instead.
            if ($op['name'] === 'concat') {
                $argument = $this->collectionArguments($op['node'], 'concat')->named('source')?->value;

                if ($argument !== null && $engine->resolve($argument)['type'] === $relationInfo['type']) {
                    continue;
                }

                return null;
            }

            // Unsupported op, including a 2nd map()/pluck() or map()+pluck() combined.
            return null;
        }

        if ($isTerminal) {
            if ($mapNode !== null || $pluckNode !== null) {
                return null; // YAGNI: map()/pluck() combined with a first()/last() terminal.
            }

            return [
                ...ValueResult::unknown(),
                'type' => class_basename($elementModel).' | null',
                'optional' => false,
                'modelFqcn' => $elementModel,
            ];
        }

        if ($mapNode === null && $pluckNode === null) {
            return [
                ...ValueResult::unknown(),
                'type' => $sequentialKeys ? $relationInfo['type'] : $this->keyedObjectArm($relationInfo['type']),
                'optional' => false,
                'modelFqcn' => $elementModel,
            ];
        }

        if ($pluckNode !== null) {
            $previousContext = $scope->closureRelationModelClass;
            $scope->closureRelationModelClass = $elementModel;

            try {
                $pluckResult = $this->analyzeVariablePluckCall($pluckNode, $scope);
            } finally {
                $scope->closureRelationModelClass = $previousContext;
            }

            // analyzeVariablePluckCall() degrades an unresolved field to 'unknown[]'; normalize to null
            // so the caller's fallthrough produces plain 'unknown' like every other unrecognized chain.
            if ($pluckResult['type'] === 'unknown[]') {
                return null;
            }

            if (! $sequentialKeys) {
                $pluckResult['type'] = $this->keyedObjectArm($pluckResult['type']);
            }

            return [...ValueResult::unknown(), ...$pluckResult];
        }

        // The map argument must be a Closure/ArrowFunction: a callable-array (`[$this, 'method']`) or a
        // bare string callable (`'strtoupper'`) is itself a valid expression node, so analyzeValueExpression()
        // would resolve *that* — 'strtoupper' → 'string', wrongly wrapped here to 'string[]'.
        /** @var MethodCall $mapNode */
        $mapArg = $this->collectionArguments($mapNode, 'map')->named('callback')?->value;

        if ($mapArg === null) {
            return null; // @codeCoverageIgnore
        }

        if (! $mapArg instanceof ArrowFunction && ! $mapArg instanceof ClosureExpr) {
            return null;
        }

        // map() passes ($value, $key), so a variadic first param collects both and never holds one element.
        if ($mapArg->params !== [] && $mapArg->params[0]->variadic) {
            return null;
        }

        $previousContext = $scope->closureRelationModelClass;
        $previousNameBindings = $scope->nameBindings();

        try {
            $scope->closureRelationModelClass = $elementModel;
            $scope->claimParameters($mapArg);

            if ($mapArg->params !== []
                && $mapArg->params[0]->var instanceof Variable
                && is_string($mapArg->params[0]->var->name)
            ) {
                $scope->varModelBindings[$mapArg->params[0]->var->name] = $elementModel;
            }

            $bodyResult = $engine->resolve($mapArg);
        } finally {
            $scope->closureRelationModelClass = $previousContext;
            $scope->restoreNameBindings($previousNameBindings);
        }

        if ($bodyResult['type'] === 'unknown') {
            return null;
        }

        // A map body entirely `EnumResource::make(...)` carries a live 'enumFqcn' through; the
        // transformer's substitution-based rewrite reproduces whatever shape results, including
        // the keyed Record arm a non-sequential filter()/sortBy() introduces.
        $mapped = ValueResult::arrayWrapType($bodyResult['type']);

        return [
            ...$bodyResult,
            'type' => $sequentialKeys ? $mapped : $this->keyedObjectArm($mapped),
            'optional' => false,
        ];
    }

    /**
     * What `$this->resource->m()` returns: the wrapped class's declaration when it has one, else the model's, through
     * the read ReceiverMethodReturnResolver::forwardedReturn() gives, as the bare call does.
     *
     * @return ValueExpressionResult|null
     */
    private function forwardedMethodReturn(string $methodName, AnalysisScope $scope): ?array
    {
        $wrappedClass = $this->resolveWrappedClass($scope);
        $class = $wrappedClass !== null && method_exists($wrappedClass, $methodName) ? $wrappedClass : $scope->modelClass;

        if ($class === null || ! method_exists($class, $methodName) || ! new ReflectionMethod($class, $methodName)->isPublic()) {
            return null;
        }

        return resolve(ReceiverMethodReturnResolver::class)->forwardedReturn($class, $methodName, $scope);
    }

    /**
     * A Carbon method on a date-cast column, `$this->published_at->format()`, reflected on the class the cast yields.
     *
     * @return ValueExpressionResult|null
     */
    private function heldPropertyMethodReturn(string $propertyName, string $methodName, AnalysisScope $scope): ?array
    {
        if ($scope->modelClass === null) {
            return null;
        }

        $resolver = resolve(ModelAttributeResolver::class);
        $cast = $resolver->getAttributes($scope->modelClass)?->firstWhere('name', $propertyName)['cast'] ?? null;

        if (! is_string($cast) || ! $resolver->isDateFamilyCast($cast)) {
            return null;
        }

        $carbonClass = str_starts_with($cast, 'immutable_') ? CarbonImmutable::class : Carbon::class;

        return resolve(ReflectedTypeAcceptor::class)
            ->accept(LaravelTsPublish::methodOrDocblockReturnTypes(new ReflectionClass($carbonClass), $methodName));
    }

    /**
     * A model-less subject's `$this->service->m()`, from the class its own property is typed as. A held model's
     * filters, toArray() and the arrays the receiver rules type are left to them.
     *
     * @return ValueExpressionResult|null
     */
    private function subjectPropertyMethodReturn(MethodCall $call, string $methodName, AnalysisScope $scope): ?array
    {
        if ($scope->modelClass !== null || $this->resolveWrappedClass($scope) !== null) {
            return null;
        }

        $propertyClass = resolve(CallMatcher::class)->resolveThisPropertyClass($scope->subjectReflection, $call->var);

        if ($propertyClass === null
            || (is_a($propertyClass, Model::class, true) && $this->callsAttributeFilter($call))
            || resolve(ReceiverMethodReturnResolver::class)->typesModelMethod($propertyClass, $methodName)
        ) {
            return null;
        }

        return resolve(SubjectMethodTypeResolver::class)->resolveOn(new ReflectionClass($propertyClass), $methodName);
    }

    /**
     * Whether a `take()` call slices from the front, where a sequentially keyed receiver stays sequential.
     *
     * A negative count takes from the tail and a non-literal count could be either, so both are rejected.
     */
    private function isFrontAnchoredTake(MethodCall $call): bool
    {
        if ($call->isFirstClassCallable()) {
            return false;
        }

        $args = $this->collectionArguments($call, 'take');

        return $args->passedCount() === 1 && ! $args->hasUnpack() && $args->named('limit')?->value instanceof Int_;
    }

    /**
     * A chain op's arguments mapped against the Eloquent collection method it calls.
     */
    private function collectionArguments(MethodCall $call, string $method): CallArguments
    {
        return CallArguments::for($call, new ReflectionMethod(EloquentCollection::class, $method));
    }
}
