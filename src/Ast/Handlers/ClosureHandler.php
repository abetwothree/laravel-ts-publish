<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;

/**
 * Closures and arrow functions used as a property value — resolves the body's return type(s),
 * falling back to the declared return-type annotation when the body itself resolves to `unknown`.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @phpstan-type ClosureAnnotationResult = array{
 *      type: string,
 *      directEnumFqcn?: class-string,
 *      modelFqcn?: class-string
 * }
 *
 * @internal
 */
final class ClosureHandler implements ExpressionHandler
{
    use InspectsAstNodes;

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [ArrowFunction::class, ClosureExpr::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        // Closures / arrow functions — body analysis first, return-type annotation as the fallback.
        $closureReturns = $this->resolveClosureReturnExpressions($expr);

        if ($closureReturns !== []) {
            // A param merely shadows a same-named outer local for this body — it must not resolve
            // through the outer binding just because no scoped binding (e.g. whenLoaded) claimed it.
            $previousLocalVarBindings = $scope->localVarBindings;

            if ($expr instanceof ArrowFunction || $expr instanceof ClosureExpr) {
                foreach ($expr->params as $param) {
                    if ($param->var instanceof Variable && is_string($param->var->name)) {
                        unset($scope->localVarBindings[$param->var->name]);
                    }
                }
            }

            try {
                $bodyResult = count($closureReturns) === 1
                    ? $engine->resolve($closureReturns[0])
                    : ValueResult::analyzeClosureUnion($closureReturns, $engine);

                if ($bodyResult['type'] !== 'unknown') {
                    return $bodyResult;
                }

                $annotationResult = $this->resolveClosureAstReturnType($expr);

                if ($annotationResult !== null) {
                    return [...$annotationResult, 'optional' => false];
                }

                return $bodyResult;
            } finally {
                $scope->localVarBindings = $previousLocalVarBindings;
            }
        }

        return null;
    }

    /**
     * Resolve an arrow function's or closure's return type annotation to a ClosureAnnotationResult.
     * Returns null when the annotation is absent, is a union/intersection, or maps to void/mixed/never
     * or an unresolvable class.
     *
     * @return ClosureAnnotationResult|null
     */
    private function resolveClosureAstReturnType(Expr $expr): ?array
    {
        if (! $expr instanceof ArrowFunction && ! $expr instanceof ClosureExpr) {
            return null;
        }

        $returnType = $expr->returnType;

        if ($returnType === null) {
            return null;
        }

        return $this->convertAstTypeNodeToTs($returnType);
    }

    /**
     * Convert a PHP-Parser return-type AST node to a ClosureAnnotationResult (type + optional FQCN).
     *
     * Returns null for union/intersection types, void/never/mixed, unresolvable classes, and types
     * carrying customImports — that import metadata cannot be represented in ValueExpressionResult.
     *
     * @return ClosureAnnotationResult|null
     */
    private function convertAstTypeNodeToTs(Node $typeNode): ?array
    {
        if ($typeNode instanceof NullableType) {
            $inner = $this->convertAstTypeNodeToTs($typeNode->type);

            if ($inner === null) {
                return null;
            }

            return [...$inner, 'type' => $inner['type'].' | null'];
        }

        if ($typeNode instanceof Identifier) {
            $phpType = $typeNode->toString();

            if (in_array($phpType, ['void', 'never', 'mixed'], true)) {
                return null;
            }

            $tsInfo = LaravelTsPublish::toTsType($phpType);

            return $tsInfo['type'] !== 'unknown' ? ['type' => $tsInfo['type']] : null;
        }

        if ($typeNode instanceof Name) {
            $phpType = $typeNode->toString();
            $tsInfo = LaravelTsPublish::toTsType($phpType);

            if ($tsInfo['type'] === 'unknown') {
                return null;
            }

            if ($tsInfo['customImports'] !== []) {
                return null;
            }

            /** @var ClosureAnnotationResult $result */
            $result = ['type' => $tsInfo['type']];

            if ($tsInfo['enumFqcns'] !== []) {
                $result['directEnumFqcn'] = $tsInfo['enumFqcns'][0];
            } elseif ($tsInfo['classFqcns'] !== []) {
                $result['modelFqcn'] = $tsInfo['classFqcns'][0];
            }

            return $result;
        }

        // UnionType / IntersectionType — fall through to body analysis
        return null;
    }
}
