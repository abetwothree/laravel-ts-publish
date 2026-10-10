<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResolver;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;

/**
 * `SomeClass::CONSTANT` / `self::CONSTANT` / `static::CONSTANT` / `SomeEnum::Case` as a value. `Foo::class` types as a
 * string; EnumResource::make(), toResource(SomeResource::class) and #[Collects] read their arguments before this runs.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class ClassConstantHandler implements ExpressionHandler
{
    public function __construct(private ValueResolver $resolver = new ValueResolver) {}

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [ClassConstFetch::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if ($expr instanceof ClassConstFetch && $expr->class instanceof Name && $expr->name instanceof Identifier) {
            $constantResult = $this->resolver->resolveClassConstant($expr, $scope);

            if ($constantResult !== null) {
                return $constantResult;
            }
        }

        return null;
    }
}
