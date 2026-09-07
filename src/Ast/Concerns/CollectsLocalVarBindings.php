<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Expression as ExpressionStmt;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\NodeFinder;

/**
 * The single `$var = expr;` binding pass. Shared because both the resource analyzer's own
 * method walk and AstEngine::bindingsFor() must read a body's variables the same way.
 *
 * @internal
 */
trait CollectsLocalVarBindings
{
    /**
     * Record top-level `$var = expr;` statements so values referencing those variables resolve.
     *
     * Skips variables written more than once — this flat list can't tell which write is live at a
     * given return branch, so binding one risks a wrong-but-plausible type instead of unknown.
     *
     * @param  array<Node\Stmt>  $stmts
     */
    protected function collectLocalVarBindings(array $stmts, AnalysisScope $scope): void
    {
        /** @var array<string, int> $writeCounts */
        $writeCounts = [];

        foreach ($this->collectWrittenVariableNames($stmts) as $name) {
            $writeCounts[$name] = ($writeCounts[$name] ?? 0) + 1;
        }

        foreach ($stmts as $stmt) {
            if ($stmt instanceof ExpressionStmt
                && $stmt->expr instanceof Assign
                && $stmt->expr->var instanceof Variable
                && is_string($stmt->expr->var->name)
                && ($writeCounts[$stmt->expr->var->name] ?? 0) === 1
            ) {
                $scope->localVarBindings[$stmt->expr->var->name] = $stmt->expr->expr;
            }
        }
    }

    /**
     * Collect every local variable name written anywhere in a statement tree (writes, mutations,
     * foreach targets, closure by-ref uses).
     *
     * By-reference call arguments are a known gap — the callee's signature isn't statically knowable.
     *
     * @param  array<Node>  $stmts
     * @return list<string>
     */
    protected function collectWrittenVariableNames(array $stmts): array
    {
        $finder = new NodeFinder;

        $writeNodes = $finder->find(
            $stmts,
            fn (Node $node): bool => $node instanceof Assign
                || $node instanceof AssignRef
                || $node instanceof AssignOp
                || $node instanceof PreInc
                || $node instanceof PostInc
                || $node instanceof PreDec
                || $node instanceof PostDec
                || $node instanceof Foreach_
                || $node instanceof ClosureExpr,
        );

        /** @var list<string> $names */
        $names = [];

        foreach ($writeNodes as $node) {
            /** @var list<Expr> $targets */
            $targets = [];

            if ($node instanceof AssignRef) {
                $targets[] = $node->var;
                $targets[] = $node->expr;
            } elseif ($node instanceof Assign || $node instanceof AssignOp
                || $node instanceof PreInc || $node instanceof PostInc
                || $node instanceof PreDec || $node instanceof PostDec) {
                $targets[] = $node->var;
            } elseif ($node instanceof Foreach_) {
                $targets[] = $node->valueVar;

                if ($node->keyVar !== null) {
                    $targets[] = $node->keyVar;
                }
            } elseif ($node instanceof ClosureExpr) {
                foreach ($node->uses as $use) {
                    if ($use->byRef) {
                        $targets[] = $use->var;
                    }
                }
            }

            foreach ($targets as $target) {
                $vars = $finder->find(
                    $target,
                    fn (Node $n): bool => $n instanceof Variable && is_string($n->name),
                );

                foreach ($vars as $var) {
                    if ($var instanceof Variable && is_string($var->name)) {
                        $names[] = $var->name;
                    }
                }
            }
        }

        return $names;
    }
}
