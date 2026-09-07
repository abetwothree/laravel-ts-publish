<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Identifier;

/**
 * Late-stage method-name rules fixed by Laravel convention, shared by the two chain handlers and
 * by KnownMethodRuleHandler, the dispatch floor. The sole implementation — the analyzer's own copy
 * went with its legacy chain.
 *
 * Requires the host to also use InspectsAstNodes, InspectsResourceSubject and ResolvesModelRelationTypes.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
trait AppliesKnownMethodRules
{
    /**
     * Late-stage rules fixed by Laravel convention (can/cannot/canAny → boolean; count/exists → number/boolean).
     *
     * count()/exists()/getKey() are receiver-gated (unlike can()) since those names are commonly overloaded;
     * getKey() is further scoped to `$this->resource->getKey()` since its type depends on the receiver's key type.
     *
     * @return ValueExpressionResult|null
     */
    protected function knownMethodRule(MethodCall|NullsafeMethodCall $expr, AnalysisScope $scope): ?array
    {
        if (! $expr->name instanceof Identifier) {
            return null; // @codeCoverageIgnore
        }

        $method = $expr->name->toString();

        if (in_array($method, ['can', 'cannot', 'canAny'], true)) {
            return [...ValueResult::unknown(), 'type' => 'boolean', 'optional' => false];
        }

        if ($method === 'getKey') {
            $isResourceReceiver = $expr->var instanceof PropertyFetch
                && $this->isThisPropertyFetch($expr->var)
                && $expr->var->name instanceof Identifier
                && $expr->var->name->toString() === 'resource';

            if (! $isResourceReceiver || $scope->modelClass === null) {
                return null;
            }

            $instance = resolve(ModelAttributeResolver::class)->getInstance($scope->modelClass);

            $type = $instance?->getKeyType() === 'int' ? 'number' : 'string';

            return [...ValueResult::unknown(), 'type' => $type, 'optional' => false];
        }

        if (! in_array($method, ['count', 'exists'], true)) {
            return null;
        }

        // Receiver must be $this->{manyRelation}, or $this->collection on a ResourceCollection —
        // Laravel populates that property with the collected resources, always a many receiver.
        if ($expr->var instanceof PropertyFetch
            && $this->isThisPropertyFetch($expr->var)
            && $expr->var->name instanceof Identifier
        ) {
            $propName = $expr->var->name->toString();

            $isManyReceiver = ($propName === 'collection' && $this->isResourceCollection($scope))
                || str_ends_with($this->resolveModelRelationTypeInfo($propName, $scope)['type'], '[]');

            if ($isManyReceiver) {
                return [
                    ...ValueResult::unknown(),
                    'type' => $method === 'count' ? 'number' : 'boolean',
                    'optional' => false,
                ];
            }
        }

        return null;
    }
}
