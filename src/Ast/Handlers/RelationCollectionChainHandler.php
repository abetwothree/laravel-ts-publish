<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\CallMatcher;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\AnalyzesPluckCalls;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\AppliesKnownMethodRules;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsResourceSubject;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesModelRelationTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesRelatedModelTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ReflectedTypeAcceptor;
use AbeTwoThree\LaravelTsPublish\Ast\SubjectMethodTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Carbon\Carbon as BaseCarbon;
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
use ReflectionNamedType;

/**
 * Non-nullsafe method calls on `$this`: collection chains rooted at a many-relation, calls on a
 * wrapped `$this->prop` receiver, and the generic `$this->method()` return-type reflection.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class RelationCollectionChainHandler implements ExpressionHandler
{
    use AnalyzesPluckCalls;
    use AppliesKnownMethodRules;
    use InspectsAstNodes;
    use InspectsResourceSubject;
    use ResolvesModelRelationTypes;
    use ResolvesRelatedModelTypes;

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [MethodCall::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        // Collection chains rooted at `$this->{manyRelation}` (e.g. `->take(5)->map(...)->values()`).
        // Must precede the `$this->anyProp->method()` branch below: a 1-deep `$this->items->count()`
        // matches both, and this returns null for it so knownMethodRule()'s count()/exists() rule wins.
        if ($expr instanceof MethodCall) {
            $chainResult = $this->analyzeRelationCollectionChain($expr, $scope, $engine);

            if ($chainResult !== null) {
                return $chainResult;
            }
        }

        // $this->anyProp->method() — e.g. $this->resource->extensions() on a backed enum or model
        if ($expr instanceof MethodCall
            && $this->isThisPropertyFetch($expr->var)
            && $expr->name instanceof Identifier
        ) {
            $info = $this->analyzeWrappedResourceMethodCall($expr, $scope);

            /** @var class-string<Model>|null $closureModelClass */
            $closureModelClass = $scope->closureRelationModelClass;

            if ($info['type'] === 'unknown' && $closureModelClass !== null) {
                $info = $this->analyzeRelatedModelMethodCall($expr->name->toString(), $scope);
            }

            return $info;
        }

        // Generic `$this->method()` — reflect the declared return type; the helper guards above ran first.
        if ($expr instanceof MethodCall
            && $expr->var instanceof Variable
            && $expr->var->name === 'this'
            && $expr->name instanceof Identifier
        ) {
            return resolve(SubjectMethodTypeResolver::class)->resolve($scope, $expr->name->toString());
        }

        return null;
    }

    /**
     * Analyze a method-call chain on `$this->{manyRelation}` of identity-preserving ops plus at most
     * one `map()`/`pluck()`, or an argless `first()`/`last()`.
     *
     * Anything else returns null and falls through — e.g. `$this->items->count()` still reaches knownMethodRule().
     *
     * @return ValueExpressionResult|null
     */
    private function analyzeRelationCollectionChain(MethodCall $call, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $identityOps = [
            'take', 'skip', 'filter', 'reject', 'values', 'unique',
            'sortBy', 'sortByDesc', 'slice', 'reverse', 'where', 'whereNotNull',
            'load', 'loadMissing',
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
                    'load', 'loadMissing' => $sequentialKeys,
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

        $previousContext = $scope->closureRelationModelClass;
        $previousVarModelBindings = $scope->varModelBindings;
        $scope->closureRelationModelClass = $elementModel;

        if ($mapArg->params !== []
            && $mapArg->params[0]->var instanceof Variable
            && is_string($mapArg->params[0]->var->name)
        ) {
            $scope->varModelBindings[$mapArg->params[0]->var->name] = $elementModel;
        }

        try {
            $bodyResult = $engine->resolve($mapArg);
        } finally {
            $scope->closureRelationModelClass = $previousContext;
            $scope->varModelBindings = $previousVarModelBindings;
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
     * Analyze `$this->anyProp->method()` by resolving the method on the wrapped class.
     *
     * @return ValueExpressionResult
     */
    private function analyzeWrappedResourceMethodCall(MethodCall $expr, AnalysisScope $scope): array
    {
        $result = ValueResult::unknown();
        $methodName = $expr->name instanceof Identifier ? $expr->name->toString() : null;

        if ($methodName === null) {
            return $result; // @codeCoverageIgnore
        }

        $wrappedClass = $this->resolveWrappedClass($scope);

        if ($wrappedClass !== null && method_exists($wrappedClass, $methodName)) {
            /** @var class-string $wrappedClass */
            $tsInfo = LaravelTsPublish::methodOrDocblockReturnTypes(new ReflectionClass($wrappedClass), $methodName);
            $accepted = resolve(ReflectedTypeAcceptor::class)->accept($tsInfo);

            if ($accepted !== null) {
                return $accepted;
            }
        } elseif ($scope->modelClass !== null && method_exists($scope->modelClass, $methodName)) {
            // @mixin-style resources: `$this->resource->commentsCount()` lives on the model.
            /** @var class-string $modelClass */
            $modelClass = $scope->modelClass;
            $tsInfo = LaravelTsPublish::methodOrDocblockReturnTypes(new ReflectionClass($modelClass), $methodName);
            $accepted = resolve(ReflectedTypeAcceptor::class)->accept($tsInfo);

            if ($accepted !== null) {
                return $accepted;
            }
        }

        // On a date-cast receiver (e.g. `created_at`) the method is a Carbon instance method reached
        // through the cast, not declared on the model — reflect it on Carbon/CarbonImmutable instead.
        if ($expr->var instanceof PropertyFetch && $expr->var->name instanceof Identifier) {
            $receiverAttr = $scope->modelClass !== null
                ? resolve(ModelAttributeResolver::class)->getAttributes($scope->modelClass)
                    ?->firstWhere('name', $expr->var->name->toString())
                : null;

            $cast = $receiverAttr['cast'] ?? null;

            if (is_string($cast) && $this->isDateFamilyCast($cast)) {
                $carbonClass = str_starts_with($cast, 'immutable_')
                    ? CarbonImmutable::class
                    : Carbon::class;

                if (! $this->carbonMethodReturnsUnimportableStringable($carbonClass, $methodName)) {
                    $tsInfo = LaravelTsPublish::methodOrDocblockReturnTypes(
                        new ReflectionClass($carbonClass),
                        $methodName,
                    );

                    $accepted = resolve(ReflectedTypeAcceptor::class)->accept($tsInfo);

                    if ($accepted !== null) {
                        return $accepted;
                    }
                }
            }
        }

        // Subject mode: a model-less scope (a controller, not a resource) resolves `$this->service`
        // from the subject's own typed property, then reflects the method on that class.
        if ($wrappedClass === null && $scope->modelClass === null) {
            $propertyClass = resolve(CallMatcher::class)->resolveThisPropertyClass($scope->subjectReflection, $expr->var);

            if ($propertyClass !== null) {
                $accepted = resolve(SubjectMethodTypeResolver::class)
                    ->resolveOn(new ReflectionClass($propertyClass), $methodName);

                if ($accepted !== null) {
                    return $accepted;
                }
            }
        }

        // Known-method rules — authorization checks and relation counts/existence.
        $known = $this->knownMethodRule($expr, $scope);

        if ($known !== null) {
            return $known;
        }

        return $result;
    }

    /**
     * Determine whether a Carbon(Immutable) method returns a __toString()-only class, not a genuine string.
     *
     * Needed since toTsType() erases Stringable classes to a bare `string` — mirrors step 5b's own condition.
     * Carbon/CarbonImmutable are excluded — their `__toString()` IS the canonical value, unlike CarbonInterval's.
     */
    private function carbonMethodReturnsUnimportableStringable(string $carbonClass, string $methodName): bool
    {
        if (! method_exists($carbonClass, $methodName)) {
            return false;
        }

        $returnType = new ReflectionMethod($carbonClass, $methodName)->getReturnType();

        if (! $returnType instanceof ReflectionNamedType) {
            return false;
        }

        $name = $returnType->getName();

        if (in_array($name, [BaseCarbon::class, CarbonImmutable::class], true)) {
            return false;
        }

        return class_exists($name)
            && ! is_a($name, Model::class, true)
            && method_exists($name, '__toString');
    }

    /**
     * Determine whether a resolved model cast belongs to the date/datetime family, including
     * immutable_* variants and the `:format` suffix on custom_datetime casts.
     */
    private function isDateFamilyCast(string $cast): bool
    {
        return in_array(explode(':', $cast)[0], [
            'date', 'datetime', 'custom_datetime', 'timestamp',
            'immutable_date', 'immutable_datetime', 'immutable_custom_datetime',
        ], true);
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
     * Add the object arm json_encode emits for a gapped or reordered collection: `X[]` → `X[] | Record<string, X>`.
     */
    private function keyedObjectArm(string $arrayType): string
    {
        return $arrayType.' | Record<string, '.substr($arrayType, 0, -2).'>';
    }

    /**
     * A chain op's arguments mapped against the Eloquent collection method it calls.
     */
    private function collectionArguments(MethodCall $call, string $method): CallArguments
    {
        return CallArguments::for($call, new ReflectionMethod(EloquentCollection::class, $method));
    }
}
