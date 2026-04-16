<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\Concerns;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\Node\Stmt\While_;

/**
 * AST node inspection and predicate helpers for resource analysis.
 */
trait InspectsAstNodes
{
    /** @var list<string> */
    protected array $conditionalMethods = [
        'when', 'whenHas', 'whenNotNull', 'whenLoaded',
        'whenCounted', 'whenAggregated', 'whenPivotLoaded', 'whenPivotLoadedAs',
    ];

    /**
     * Check if a static call's first argument is a conditional expression
     * (e.g. $this->whenLoaded('relation'), $this->when(...), etc.).
     */
    protected function hasConditionalArgument(StaticCall $call): bool
    {
        if ($call->isFirstClassCallable()) {
            return false;
        }

        $args = $call->getArgs();

        if (count($args) < 1) {
            return false;
        }

        $inner = $args[0]->value;

        foreach ($this->conditionalMethods as $method) {
            if ($this->isThisMethodCall($inner, $method)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a `new Resource(...)` call's first argument is a conditional expression.
     */
    protected function hasConditionalNewArgument(New_ $expr): bool
    {
        $args = $expr->getArgs();

        if (count($args) < 1) {
            return false; // @codeCoverageIgnore
        }

        $inner = $args[0]->value;

        foreach ($this->conditionalMethods as $method) {
            if ($this->isThisMethodCall($inner, $method)) {
                return true;
            }
        }

        return false;
    }

    protected function isThisMethodCall(Expr $expr, string $methodName): bool
    {
        return $expr instanceof MethodCall
            && $expr->var instanceof Variable
            && $expr->var->name === 'this'
            && $expr->name instanceof Identifier
            && $expr->name->toString() === $methodName;
    }

    protected function isThisPropertyFetch(Expr $expr): bool
    {
        return $expr instanceof PropertyFetch
            && $expr->var instanceof Variable
            && $expr->var->name === 'this';
    }

    protected function resolveKeyName(Expr $key): ?string
    {
        if ($key instanceof String_) {
            return $key->value;
        }

        return null;
    }

    protected function resolveStaticCallClassName(StaticCall $call): ?string
    {
        if ($call->class instanceof Name) {
            return $call->class->toString();
        }

        return null; // @codeCoverageIgnore
    }

    protected function isEnumResourceClass(string $fqcn): bool
    {
        return $fqcn === EnumResource::class
            || $fqcn === 'EnumResource'
            || is_a($fqcn, EnumResource::class, true);
    }

    protected function isResourceClass(string $fqcn): bool
    {
        return class_exists($fqcn) && is_a($fqcn, JsonResource::class, true);
    }

    /**
     * Check if an expression is a parent::toArray() call.
     */
    protected function isParentToArrayCall(Expr $expr): bool
    {
        return $expr instanceof StaticCall
            && $expr->class instanceof Name
            && $expr->class->toLowerString() === 'parent'
            && $expr->name instanceof Identifier
            && $expr->name->toString() === 'toArray';
    }

    /**
     * Collect ALL return expressions from a closure or arrow function, producing
     * one entry per distinct return path. Used to build union types when a closure
     * has multiple return branches (e.g. guard clause + data array).
     *
     * - ArrowFunction: single-element list with the expression body.
     * - Closure: collects every top-level Return_ with a non-null expression,
     *   recursing into if/else and switch blocks but NOT into nested closures.
     *
     * @return list<Expr>
     */
    protected function resolveClosureReturnExpressions(Expr $expr): array
    {
        if ($expr instanceof ArrowFunction) {
            return [$expr->expr];
        }

        if ($expr instanceof ClosureExpr) {
            return $this->collectReturnExpressions($expr->stmts);
        }

        return [];
    }

    /**
     * Recursively collect Return_ expressions from a list of statements,
     * descending into control-flow blocks (if/else, switch, try/catch, foreach,
     * for, while, do-while) but NOT into nested closures or arrow functions.
     *
     * @param  array<Stmt>  $stmts
     * @return list<Expr>
     */
    protected function collectReturnExpressions(array $stmts): array
    {
        /** @var list<Expr> $returns */
        $returns = [];

        foreach ($stmts as $stmt) {
            if ($stmt instanceof Return_ && $stmt->expr !== null) {
                $returns[] = $stmt->expr;

                continue;
            }

            // Descend into control-flow blocks
            if ($stmt instanceof If_) {
                $returns = [...$returns, ...$this->collectReturnExpressions($stmt->stmts)];

                foreach ($stmt->elseifs as $elseif) {
                    $returns = [...$returns, ...$this->collectReturnExpressions($elseif->stmts)];
                }

                if ($stmt->else !== null) {
                    $returns = [...$returns, ...$this->collectReturnExpressions($stmt->else->stmts)];
                }

                continue;
            }

            if ($stmt instanceof Switch_) {
                foreach ($stmt->cases as $case) {
                    $returns = [...$returns, ...$this->collectReturnExpressions($case->stmts)];
                }

                continue;
            }

            if ($stmt instanceof TryCatch) {
                $returns = [...$returns, ...$this->collectReturnExpressions($stmt->stmts)];

                foreach ($stmt->catches as $catch) {
                    $returns = [...$returns, ...$this->collectReturnExpressions($catch->stmts)];
                }

                if ($stmt->finally !== null) {
                    $returns = [...$returns, ...$this->collectReturnExpressions($stmt->finally->stmts)];
                }

                continue;
            }

            // Loop blocks (foreach, for, while, do-while)
            if ($stmt instanceof Foreach_
                || $stmt instanceof For_
                || $stmt instanceof While_) {
                $returns = [...$returns, ...$this->collectReturnExpressions($stmt->stmts)];

                continue;
            }

            if ($stmt instanceof Do_) {
                $returns = [...$returns, ...$this->collectReturnExpressions($stmt->stmts)];
            }
        }

        return $returns;
    }
}
