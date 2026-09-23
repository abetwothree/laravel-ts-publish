<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\PropertyDocblockTypeReader;
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
     * Record top-level `$var = expr;` statements so values referencing those variables resolve, and the type an inline
     * `@var` on one declares.
     *
     * The expression binding skips variables written more than once — this flat list can't tell which write is live at
     * a given return branch, so binding one risks a wrong-but-plausible type instead of unknown.
     *
     * @param  array<Node\Stmt>  $stmts
     */
    protected function collectLocalVarBindings(array $stmts, AnalysisScope $scope): void
    {
        $writes = $this->collectVariableWrites($stmts);
        $writeCounts = array_count_values(array_column($writes, 0));

        foreach ($stmts as $stmt) {
            if (! $stmt instanceof ExpressionStmt
                || ! $stmt->expr instanceof Assign
                || ! $stmt->expr->var instanceof Variable
                || ! is_string($stmt->expr->var->name)
            ) {
                continue;
            }

            $name = $stmt->expr->var->name;

            if (($writeCounts[$name] ?? 0) === 1) {
                $scope->localVarBindings[$name] = $stmt->expr->expr;
            }

            $this->bindDeclaredType($stmt, $name, $stmts, $writes, $scope);
        }
    }

    /**
     * Collect every local variable name written anywhere in a statement tree (writes, mutations,
     * foreach targets, closure by-ref uses).
     *
     * @param  array<Node>  $stmts
     * @return list<string>
     */
    protected function collectWrittenVariableNames(array $stmts): array
    {
        return array_column($this->collectVariableWrites($stmts), 0);
    }

    /**
     * Every local variable write in a statement tree, as the name written and the node that writes it.
     *
     * By-reference call arguments are a known gap — the callee's signature isn't statically knowable.
     *
     * @param  array<Node>  $stmts
     * @return list<array{string, Node}>
     */
    protected function collectVariableWrites(array $stmts): array
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

        /** @var list<array{string, Node}> $writes */
        $writes = [];

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
                        $writes[] = [$var->name, $node];
                    }
                }
            }
        }

        return $writes;
    }

    /**
     * The writes to a variable that can still land once the body reaches an offset: each that does not end before it.
     *
     * The one position-ordered rule both the early-exit guard pass and an inline `@var` read a variable's writes by.
     *
     * @param  list<array{string, Node}>  $writes
     * @return list<Node>
     */
    protected function writesFrom(string $name, int $offset, array $writes): array
    {
        $from = [];

        foreach ($writes as [$written, $node]) {
            if ($written === $name && $node->getEndFilePos() >= $offset) {
                $from[] = $node;
            }
        }

        return $from;
    }

    /**
     * Bind a variable to the type an inline `@var` on its assignment declares, for the reads after that statement and
     * before the top-level statement holding the variable's next write.
     *
     * A top-level statement runs once, so a read in an earlier one never sees a later write; a loop holding both is one
     * statement, which is why the span ends where the writing statement starts rather than at the write.
     *
     * @param  array<Node\Stmt>  $stmts
     * @param  list<array{string, Node}>  $writes
     */
    private function bindDeclaredType(
        ExpressionStmt $assignment,
        string $name,
        array $stmts,
        array $writes,
        AnalysisScope $scope,
    ): void {
        $tag = resolve(PropertyDocblockTypeReader::class)->extractVarTag((string) $assignment->getDocComment()?->getText());

        if ($tag === null || ($tag[1] !== null && $tag[1] !== $name)) {
            return;
        }

        $before = null;

        foreach ($this->writesFrom($name, $assignment->getStartFilePos(), $writes) as $write) {
            if ($write === $assignment->expr) {
                continue;
            }

            foreach ($stmts as $stmt) {
                if ($stmt->getStartFilePos() <= $write->getStartFilePos() && $write->getEndFilePos() <= $stmt->getEndFilePos()) {
                    $before = min($before ?? $stmt->getStartFilePos(), $stmt->getStartFilePos());

                    break;
                }
            }
        }

        // A second write in the assigning statement itself may be the one its reads see.
        if ($before !== null && $before <= $assignment->getStartFilePos()) {
            return;
        }

        $scope->varDocBindings[$name][] = [
            'type' => $tag[0],
            'context' => $scope->declaringFileClass,
            'after' => $assignment->getEndFilePos(),
            'before' => $before,
        ];
    }
}
