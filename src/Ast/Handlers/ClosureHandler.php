<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\CollectsLocalVarBindings;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesModelRelationTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use ReflectionClass;

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
    use CollectsLocalVarBindings;
    use InspectsAstNodes;
    use ResolvesModelRelationTypes;

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [ArrowFunction::class, ClosureExpr::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if (! $expr instanceof ArrowFunction && ! $expr instanceof ClosureExpr) {
            return null;
        }

        // Closures / arrow functions — body analysis first, return-type annotation as the fallback.
        $closureReturns = $this->resolveClosureReturnExpressions($expr);

        if ($closureReturns !== []) {
            $previousNameBindings = $scope->nameBindings();

            try {
                // A param shadows a same-named outer binding in every table, unless a writer claimed and bound it.
                $scope->releaseUnclaimedParameters($expr);
                // Before the body is seeded, so a write that replaces a typed parameter ends its binding.
                $this->bindDeclaredParameters($expr, $scope);
                $this->markFilledDefaults($expr, $scope);

                if ($expr instanceof ClosureExpr) {
                    $this->seedBody($expr->stmts, $scope);
                    $this->bindForeachLoopVariables($expr->stmts, $scope);
                }

                $bodyResult = count($closureReturns) === 1
                    ? $engine->resolve($closureReturns[0])
                    : ValueResult::analyzeClosureUnion($closureReturns, $engine, $scope);

                if ($bodyResult['type'] !== 'unknown') {
                    return $bodyResult;
                }

                $annotationResult = $this->resolveClosureAstReturnType($expr);

                if ($annotationResult !== null) {
                    return [...$annotationResult, 'optional' => false];
                }

                return $bodyResult;
            } finally {
                $scope->restoreNameBindings($previousNameBindings);
            }
        }

        return null;
    }

    /**
     * Bind each parameter whose native type names one class to that class, where no writer bound the name to a class:
     * a non-null concrete model in varModelBindings, any other class, or a nullable one, in varDeclaredBindings, a
     * Request included. PHP throws for any other argument, so the type holds for every call.
     */
    private function bindDeclaredParameters(ArrowFunction|ClosureExpr $closure, AnalysisScope $scope): void
    {
        foreach ($closure->params as $param) {
            $name = $param->var instanceof Variable && is_string($param->var->name) ? $param->var->name : null;
            $type = $param->type instanceof NullableType ? $param->type->type : $param->type;
            $class = $type instanceof Name && ! $type->isSpecialClassName() ? $type->toString() : null;

            if ($name === null
                || $class === null
                || $param->variadic
                || (! class_exists($class) && ! interface_exists($class))
                || $scope->bindsClassName($name)
                || $this->bindsClassOrNull($scope->varValueBindings[$name] ?? null)
            ) {
                continue;
            }

            $nullable = $param->type instanceof NullableType
                || ($param->default instanceof ConstFetch && $param->default->name->toLowerString() === 'null');

            if (! $nullable && is_subclass_of($class, Model::class) && ! new ReflectionClass($class)->isAbstract()) {
                $scope->varModelBindings[$name] = $class;
            } else {
                $scope->varDeclaredBindings[$name] = [$class];
            }
        }
    }

    /**
     * Whether a value a writer bound names a class of its own, such as an enum case, or is only null, as an unpassed
     * `= null` default is: either says more about the parameter than the class its type declares.
     *
     * @param  ValueExpressionResult|null  $value
     */
    private function bindsClassOrNull(?array $value): bool
    {
        return $value !== null && ($value['type'] === 'null'
            || isset($value['modelFqcn'])
            || isset($value['enumFqcn'])
            || isset($value['directEnumFqcn'])
            || isset($value['resourceFqcn']));
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
