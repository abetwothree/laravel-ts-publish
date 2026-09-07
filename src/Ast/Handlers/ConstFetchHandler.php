<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ConstFetch;

/**
 * Bare `null`/`true`/`false` constant fetches.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class ConstFetchHandler implements ExpressionHandler
{
    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [ConstFetch::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if ($expr instanceof ConstFetch) {
            $constName = $expr->name->toLowerString();

            if ($constName === 'null') {
                return ['type' => 'null', 'optional' => false];
            }

            if (in_array($constName, ['true', 'false'], true)) {
                return ['type' => 'boolean', 'optional' => false];
            }
        }

        // A user-defined constant name — the legacy chain never resolved these either.
        return null;
    }
}
