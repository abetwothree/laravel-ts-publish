<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;

/**
 * Flatten a `$this->a->b?->c` property-fetch chain into ordered steps.
 *
 * Shared by PropertyChainHandler and MethodChainHandler, which sit on disjoint node classes but
 * decompose the same receiver shape.
 *
 * @internal
 */
trait DecomposesPropertyChains
{
    /**
     * Decompose a property-fetch expression rooted at `$this` into ordered `{name, nullable}` steps,
     * where `nullable` marks a `?->` access. Returns null if the root is not `$this`.
     *
     * @return list<array{name: string, nullable: bool}>|null
     */
    protected function decomposePropertyChain(Expr $expr): ?array
    {
        /** @var list<array{name: string, nullable: bool}> $chain */
        $chain = [];
        $current = $expr;

        while ($current instanceof PropertyFetch || $current instanceof NullsafePropertyFetch) {
            if (! $current->name instanceof Identifier) {
                return null;
            }

            $chain[] = [
                'name' => $current->name->toString(),
                'nullable' => $current instanceof NullsafePropertyFetch,
            ];

            $current = $current->var;
        }

        if (! $current instanceof Variable || $current->name !== 'this') {
            return null;
        }

        return array_reverse($chain);
    }
}
