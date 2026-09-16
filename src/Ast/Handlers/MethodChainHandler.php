<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\AppliesKnownMethodRules;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\DecomposesPropertyChains;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsResourceSubject;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesModelRelationTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Identifier;

/**
 * Nullsafe method-call chains rooted at `$this` — `$this->user?->fullName()` — resolved on the
 * terminal relation model. The `?->` operator always makes the result nullable. A chain that does not
 * end on a relation, or a method it cannot type, declines to ReceiverMethodCallHandler.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class MethodChainHandler implements ExpressionHandler
{
    use AppliesKnownMethodRules;
    use DecomposesPropertyChains;
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
        if (! $expr instanceof NullsafeMethodCall) {
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

        /** @var class-string<Model>|null $currentModel */
        $currentModel = $scope->closureRelationModelClass ?? $scope->modelClass;

        if ($currentModel === null) {
            return ValueResult::unknown();
        }

        $resolver = resolve(ModelAttributeResolver::class);

        $rootedAtResource = false;

        // `$this->resource` is the resource's own model even inside a closure bound to a relation's model.
        if ($chain[0]['name'] === 'resource'
            && $scope->modelClass !== null
            && $resolver->resolveRelation($scope->modelClass, 'resource')['type'] === 'unknown'
        ) {
            $currentModel = $scope->modelClass;
            array_shift($chain);
            $rootedAtResource = true;
        }

        if ($chain === []) {
            return ValueResult::unknown();
        }

        $count = count($chain);

        // Inside a whenLoaded closure the first step may be the resource's proxy to the already-loaded
        // relation model (`$this->categoryRel` in `whenLoaded('categoryRel', ...)`) — skip it.
        $startIndex = 0;

        if (! $rootedAtResource && $scope->closureRelationModelClass !== null) {
            $firstRelation = $resolver->resolveRelation($currentModel, $chain[0]['name']);

            if ($firstRelation['type'] === 'unknown') {
                $startIndex = 1;
            }
        }

        for ($i = $startIndex; $i < $count - 1; $i++) {
            $relationInfo = $resolver->resolveRelation($currentModel, $chain[$i]['name']);

            if ($relationInfo['type'] === 'unknown' || $relationInfo['modelFqcn'] === null) {
                return ValueResult::unknown();
            }

            /** @var class-string<Model> $relatedModel */
            $relatedModel = $relationInfo['modelFqcn'];
            $currentModel = $relatedModel;
        }

        if ($startIndex <= $count - 1) {
            $lastStep = $chain[$count - 1];
            $relationInfo = $resolver->resolveRelation($currentModel, $lastStep['name']);

            // Only a resolved relation moves the receiver; anything else belongs to the receiver-type handler.
            if ($relationInfo['modelFqcn'] === null) {
                return ValueResult::unknown();
            }

            $currentModel = $relationInfo['modelFqcn'];
        }

        $tsInfo = $resolver->resolveMethodReturnType($currentModel, $methodName);

        if ($tsInfo['type'] === '' || TsTypeString::isUnknownOnly($tsInfo['type'])) {
            // Same convention rules RelationCollectionChainHandler uses for the non-nullsafe chain.
            $tsInfo = $this->knownMethodRule($call, $scope) ?? ValueResult::unknown();
        }

        if (TsTypeString::isUnknownOnly($tsInfo['type'])) {
            return ValueResult::unknown();
        }

        $type = str_ends_with($tsInfo['type'], ' | null')
            ? $tsInfo['type']
            : $tsInfo['type'].' | null';

        return ['type' => $type, 'optional' => false];
    }
}
