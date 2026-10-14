<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\AppliesKnownMethodRules;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\DecomposesPropertyChains;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\FiltersAttributeKeys;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsResourceSubject;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesModelRelationTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverMethodReturnResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ReflectedTypeAcceptor;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Identifier;

/**
 * Nullsafe method-call chains rooted at `$this` — `$this->user?->fullName()` — resolved on the terminal relation model.
 * The `?->` operator always makes the result nullable. A chain not ending on a relation, a method it cannot type, an
 * `only()`/`except()` filter, or a model method the receiver rules type declines.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class MethodChainHandler implements ExpressionHandler
{
    use AppliesKnownMethodRules;
    use DecomposesPropertyChains;
    use FiltersAttributeKeys;
    use InspectsAstNodes;
    use InspectsResourceSubject;
    use ResolvesModelRelationTypes;

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [NullsafeMethodCall::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        // A filter belongs to RelationFilterHandler or the receiver rules, which know what it returns.
        if (! $expr instanceof NullsafeMethodCall || $this->callsAttributeFilter($expr)) {
            return null;
        }

        $result = $this->analyzeMethodChain($expr, $scope);

        return TsTypeString::isUnknownOnly($result['type']) ? null : $result;
    }

    /**
     * Analyze a nullsafe method-call chain rooted at `$this`, traversing relations to the terminal
     * model and resolving the method's return type on it. The `?->` operator makes the result nullable.
     *
     * @return ValueExpressionResult
     */
    private function analyzeMethodChain(NullsafeMethodCall $call, AnalysisScope $scope): array
    {
        $methodName = $call->name instanceof Identifier ? $call->name->toString() : null;

        if ($methodName === null) {
            return ValueResult::unknown();
        }

        $chain = $this->decomposePropertyChain($call->var);

        if ($chain === null || $chain === []) {
            return ValueResult::unknown();
        }

        // `$this` inside a relation's closure is still the resource, so the closure never moves where the chain starts.
        /** @var class-string<Model>|null $currentModel */
        $currentModel = $scope->modelClass;

        if ($currentModel === null) {
            return ValueResult::unknown();
        }

        $resolver = resolve(ModelAttributeResolver::class);

        // The proxy is the API resource's own model, even one with a `resource` relation; elsewhere it is a member.
        if ($chain[0]['name'] === 'resource' && $scope->hasResourceProxy()) {
            array_shift($chain);
        }

        if ($chain === []) {
            return ValueResult::unknown();
        }

        $count = count($chain);

        for ($i = 0; $i < $count - 1; $i++) {
            $relationInfo = $resolver->resolveRelation($currentModel, $chain[$i]['name']);

            if ($relationInfo['type'] === 'unknown' || $relationInfo['modelFqcn'] === null) {
                return ValueResult::unknown();
            }

            /** @var class-string<Model> $relatedModel */
            $relatedModel = $relationInfo['modelFqcn'];
            $currentModel = $relatedModel;
        }

        $relationInfo = $resolver->resolveRelation($currentModel, $chain[$count - 1]['name']);

        // Only a resolved relation moves the receiver; anything else belongs to the receiver-type handler.
        if ($relationInfo['modelFqcn'] === null) {
            return ValueResult::unknown();
        }

        $currentModel = $relationInfo['modelFqcn'];

        // The receiver rules type a model's own toArray() and loose arrays, and a relation collection's toArray().
        if (resolve(ReceiverMethodReturnResolver::class)->typesModelMethod($currentModel, $methodName)
            && ($methodName === 'toArray' || ! str_ends_with($relationInfo['type'], '[]'))
        ) {
            return ValueResult::unknown();
        }

        $tsInfo = $resolver->resolveMethodReturnType($currentModel, $methodName);

        if ($tsInfo['type'] === '' || TsTypeString::isUnknownOnly($tsInfo['type'])) {
            // The convention rules KnownMethodRuleHandler applies to a non-nullsafe call.
            $result = $this->knownMethodRule($call, $scope) ?? ValueResult::unknown();
        } else {
            // The channels carry the token's import; a class with no published file, such as JsonResource, has none.
            $accepted = resolve(ReflectedTypeAcceptor::class)->accept($tsInfo);
            $result = $accepted !== null && ValueResult::namesOnlyPublishedModels($accepted)
                ? $accepted
                : ValueResult::unknown();
        }

        if (TsTypeString::isUnknownOnly($result['type'])) {
            return ValueResult::unknown();
        }

        $type = str_ends_with($result['type'], ' | null')
            ? $result['type']
            : $result['type'].' | null';

        return [...$result, 'type' => $type, 'optional' => false];
    }
}
