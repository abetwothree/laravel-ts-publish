<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Contracts;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use PhpParser\Node\Expr;

/**
 * Claims a set of php-parser Expr node classes and resolves a matched expression to its TypeScript
 * type, or declines so the dispatcher tries the next handler. See ExpressionDispatcher.
 *
 * @phpstan-import-type TypesImportMap from Datable
 *
 * @phpstan-type ValueExpressionResult = array{
 *      type: string,
 *      optional: bool,
 *      enumFqcn?: class-string,
 *      directEnumFqcn?: class-string,
 *      resourceFqcn?: class-string,
 *      modelFqcn?: class-string,
 *      embeddedEnumFqcns?: list<class-string>,
 *      embeddedEnumResourceFqcns?: list<class-string>,
 *      embeddedModelFqcns?: list<class-string>,
 *      embeddedResourceFqcns?: list<class-string>,
 *      multiEnumResourceFqcns?: list<class-string>,
 *      customImports?: TypesImportMap,
 *      wrapIsCollection?: bool,
 *      directIsArray?: bool
 * }
 *
 * @internal
 */
interface ExpressionHandler
{
    /**
     * The Expr classes (concrete or abstract) this handler is a candidate for.
     *
     * @return list<class-string<Expr>>
     */
    public function nodeClasses(): array;

    /**
     * Resolve a claimed expression, or return null to decline and fall through to the next handler.
     *
     * @return ValueExpressionResult|null
     */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array;
}
