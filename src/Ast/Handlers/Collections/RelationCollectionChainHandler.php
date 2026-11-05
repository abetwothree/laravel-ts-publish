<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers\Collections;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallSite;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Callbacks\BindsMapCallbacks;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Collections\AnalyzesPluckCalls;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Collections\SpellsKeyedCollections;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Models\FiltersAttributeKeys;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Models\ResolvesModelRelationTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Models\ResolvesRelatedModelTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Resources\InspectsResourceSubject;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Members\SubjectHelperReturnResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Members\SubjectMethodTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\CallMatcher;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\CollectionSteps;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverMethodReturnResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ReflectedTypeAcceptor;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\ArrayArms;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
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
    use BindsMapCallbacks;
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

        // Collection chains rooted at `$this->{manyRelation}` or `$this->resource->{manyRelation}` (e.g. `->take(5)`).
        if ($expr instanceof MethodCall) {
            $chainResult = $this->analyzeRelationCollectionChain($expr, $scope, $engine);

            if ($chainResult !== null) {
                return $this->chainShortCircuits($expr) ? $this->withNull($chainResult) : $chainResult;
            }
        }

        // `$this->resource->m()` runs what a bare `$this->m()` forwards to, so both publish alike, a vague answer too.
        if ($expr instanceof MethodCall && $this->isProxyFetch($expr->var, $scope) && $expr->name instanceof Identifier) {
            return $this->forwardedMethodReturn($expr, $expr->name->toString(), $scope, $engine);
        }

        // Any other `$this->prop->m()` types from the class the property holds, never the scope's model: here only on a
        // date-cast column or a model-less subject's own property, and otherwise in ReceiverMethodCallHandler.
        if ($expr instanceof MethodCall
            && $expr->var instanceof PropertyFetch
            && $this->isThisPropertyFetch($expr->var)
            && $expr->var->name instanceof Identifier
            && $expr->name instanceof Identifier
        ) {
            $property = $expr->var->name->toString();

            return $this->heldPropertyMethodReturn($property, $expr->name->toString(), $scope)
                ?? $this->subjectPropertyMethodReturn($expr, $property, $expr->name->toString(), $scope, $engine);
        }

        // Generic `$this->method()` — reflect the declared return type, else read a helper that wraps an enum; the
        // helper guards above ran first.
        if ($expr instanceof MethodCall
            && $expr->var instanceof Variable
            && $expr->var->name === 'this'
            && $expr->name instanceof Identifier
        ) {
            $site = new CallSite($expr, $scope, $engine);

            return resolve(SubjectMethodTypeResolver::class)->resolve($scope, $expr->name->toString(), $site)
                ?? resolve(SubjectHelperReturnResolver::class)->resolveEnumResourceReturn($expr, $scope, $engine);
        }

        return null;
    }

    /**
     * Analyze a method-call chain on `$this->{manyRelation}` of identity-preserving ops plus at most
     * one `map()`/`pluck()` or `keys()`, or an argless `first()`/`last()`.
     *
     * Anything else returns null and falls through — e.g. `$this->items->count()` reaches the count() rule.
     *
     * @return ValueExpressionResult|null
     */
    private function analyzeRelationCollectionChain(MethodCall $call, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        [$steps, $root] = CollectionSteps::chain($call);

        // `$this->resource?->relation`: the `?->` ends the chain as null where the resource wraps none.
        $rootsAtMember = $root instanceof PropertyFetch
            ? $this->isModelMemberFetch($root, $scope)
            : $root instanceof NullsafePropertyFetch && $this->isProxyFetch($root->var, $scope);

        if (! ($root instanceof PropertyFetch || $root instanceof NullsafePropertyFetch) || ! $rootsAtMember || ! $root->name instanceof Identifier) {
            return null;
        }

        $relationInfo = $this->resolveModelRelationTypeInfo($root->name->toString(), $scope);

        if (! str_ends_with($relationInfo['type'], '[]') || $relationInfo['modelFqcn'] === null) {
            return null;
        }

        $elementModel = $relationInfo['modelFqcn'];

        // An argless first()/last() as the outermost step ends the chain with a single element or null. The chain holds
        // a step here: one by a dynamic name roots it at that call, which the member check above declines.
        $outermost = $steps[count($steps) - 1];
        $terminalOp = $outermost['name'];
        $isTerminal = CollectionSteps::elementCall($outermost['node'], $terminalOp)
            && ! $outermost['node']->isFirstClassCallable()
            && $this->collectionArguments($outermost['node'], $terminalOp)->isEmpty();

        if ($isTerminal) {
            array_pop($steps);
        }

        $pluckNode = null;
        $walk = CollectionSteps::walk($steps, true, function (array $step, bool $keyed) use (&$pluckNode, $relationInfo, $engine): ?bool {
            if ($step['name'] === 'pluck' && $pluckNode === null) {
                $pluckNode = $step['node'];

                return $this->collectionArguments($step['node'], 'pluck')->named('key') !== null;
            }

            // concat() is identity only when it appends the very same collection type; a different
            // element type makes a genuinely different collection, so that declines instead.
            $argument = $step['name'] === 'concat' ? $this->collectionArguments($step['node'], 'concat')->named('source')?->value : null;

            return $argument !== null && $engine->resolve($argument)['type'] === $relationInfo['type'] ? $keyed : null;
        });

        // A 2nd map()/pluck() or map()+pluck() combined declines.
        if ($walk === null || ($walk['map'] !== null && $pluckNode !== null)) {
            return null;
        }

        $mapNode = $walk['map'];
        $sequentialKeys = ! $walk['keyed'];

        // keys() lists the relation's numbers, which no model's map(), pluck() or terminal reads.
        if ($walk['keys']) {
            $keys = (string) ArrayArms::keysList($relationInfo['type']);

            return $mapNode !== null || $pluckNode !== null || $isTerminal
                ? null
                : ['type' => $sequentialKeys ? $keys : ArrayArms::keyedList($keys), 'optional' => false];
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
                'type' => $sequentialKeys ? $relationInfo['type'] : ArrayArms::keyedList($relationInfo['type']),
                'optional' => false,
                'modelFqcn' => $elementModel,
            ];
        }

        if ($pluckNode !== null) {
            $pluckResult = $this->analyzeVariablePluckCall($pluckNode, $scope, $elementModel);

            // analyzeVariablePluckCall() degrades an unresolved field to 'unknown[]'; normalize to null
            // so the caller's fallthrough produces plain 'unknown' like every other unrecognized chain.
            if ($pluckResult['type'] === 'unknown[]') {
                return null;
            }

            if (! $sequentialKeys) {
                $pluckResult['type'] = ArrayArms::keyedList($pluckResult['type']);
            }

            return [...ValueResult::unknown(), ...$pluckResult];
        }

        // filter() reaches a mapped item only where the chain hands back an array.
        /** @var MethodCall $mapNode */
        $bodyResult = $this->resolveMapCallback(
            $this->collectionArguments($mapNode, 'map')->named('callback')?->value,
            $elementModel,
            null,
            $terminalOp === 'all',
            $scope,
            $engine,
        );

        return $this->mappedList($bodyResult, ! $sequentialKeys, false);
    }

    /**
     * Adds `| null` to a result once.
     *
     * @param  ValueExpressionResult  $result
     * @return ValueExpressionResult
     */
    private function withNull(array $result): array
    {
        return [...$result, 'type' => TsTypeString::withNullArm($result['type'])];
    }

    /**
     * What `$this->resource->m()` returns: the read SubjectMethodTypeResolver::resolveForwarded() gives the bare call.
     *
     * @return ValueExpressionResult|null
     */
    private function forwardedMethodReturn(MethodCall $call, string $methodName, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        return resolve(SubjectMethodTypeResolver::class)
            ->resolveForwarded($scope, $methodName, new CallSite($call, $scope, $engine));
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
    private function subjectPropertyMethodReturn(
        MethodCall $call,
        string $property,
        string $methodName,
        AnalysisScope $scope,
        ExpressionEngine $engine,
    ): ?array {
        if ($scope->modelClass !== null || $this->resolveWrappedClass($scope) !== null) {
            return null;
        }

        $propertyClass = resolve(CallMatcher::class)->resolveThisPropertyClass($scope->thisMemberClass($property, false), $call->var);

        if ($propertyClass === null
            || (is_a($propertyClass, Model::class, true) && $this->callsAttributeFilter($call))
            || resolve(ReceiverMethodReturnResolver::class)->typesModelMethod($propertyClass, $methodName)
        ) {
            return null;
        }

        return resolve(SubjectMethodTypeResolver::class)
            ->forwardedOn($propertyClass, $methodName, $scope, new CallSite($call, $scope, $engine));
    }

    /**
     * A chain op's arguments mapped against the Eloquent collection method it calls.
     */
    private function collectionArguments(MethodCall $call, string $method): CallArguments
    {
        return CallArguments::for($call, new ReflectionMethod(EloquentCollection::class, $method));
    }
}
