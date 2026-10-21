<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\BooleanAnd;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\Exit_;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Stmt\Block;
use PhpParser\Node\Stmt\Break_;
use PhpParser\Node\Stmt\Case_;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Continue_;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\ElseIf_;
use PhpParser\Node\Stmt\Expression as ExpressionStmt;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\Node\Stmt\While_;

/**
 * The guard pass: an `if` condition narrows a variable by its `instanceof` tests, and proves reads non-null, in the
 * blocks it runs and past the branches that leave. CollectsLocalVarBindings composes it: its write collection is what
 * decides a binding or a proof is safe, and both passes read the same statement list.
 *
 * @phpstan-type InstanceofProofs = list<array{string, non-empty-list<class-string>, Expr}>
 *
 * @phpstan-import-type VariableWrites from CollectsLocalVarBindings
 *
 * @internal
 */
trait CollectsInstanceofGuards
{
    use ReadsInstanceofChains;
    use ReadsNonNullGuards;

    /**
     * Bind the variables an `instanceof` test proves to hold a class: in each block an `if` chain runs, where its test
     * holds and every earlier one failed, and past the chain to the end of its block, where the tests of its leading
     * branches that always leave failed. Then prove the reads the body's guards show non-null.
     *
     * A binding carries the offsets it holds between, so a return placed before a guard, or a guard's own body, still
     * reads the variable unnarrowed. A variable written between the test and the end of what it narrows is not bound.
     *
     * @param  array<Node\Stmt>  $stmts
     * @param  VariableWrites|null  $writes  the body's writes, when the caller has scanned them
     */
    protected function collectInstanceofGuards(array $stmts, AnalysisScope $scope, ?array $writes = null): void
    {
        $writes ??= $this->collectVariableWrites($stmts, $scope->subjectReflection, $scope->declaringFileClass);

        $this->bindBlockGuards($stmts, null, $scope, $writes);
        $this->proveGuardedReads($stmts, $scope, $writes);
    }

    /**
     * Prove the reads a closure's own guards show non-null while its body runs; an arrow function holds no statement.
     */
    protected function proveClosureGuards(Expr $closure, AnalysisScope $scope): void
    {
        if ($closure instanceof ClosureExpr) {
            $this->proveGuardedReads(
                $closure->stmts,
                $scope,
                $this->collectVariableWrites($closure->stmts, $scope->subjectReflection, $scope->declaringFileClass),
            );
        }
    }

    /**
     * Whether a block always leaves the function: its last statement returns, throws or exits, and no `break` or
     * `continue` in it leaves the block first.
     *
     * @param  array<Node\Stmt>  $stmts
     */
    private function alwaysExits(array $stmts): bool
    {
        $last = $stmts === [] ? null : $stmts[array_key_last($stmts)];

        return ($last instanceof Return_
            || ($last instanceof ExpressionStmt && ($last->expr instanceof Throw_ || $last->expr instanceof Exit_)))
            && ! $this->leavesEarly($stmts, 0);
    }

    /**
     * Whether a `break` or `continue` in some nodes leaves past the loops and `switch` statements around it there,
     * $depth of them already, outside any nested function.
     *
     * @param  array<mixed>|Node  $nodes
     */
    private function leavesEarly(array|Node $nodes, int $depth): bool
    {
        foreach (is_array($nodes) ? $nodes : [$nodes] as $node) {
            if (is_array($node)) {
                if ($this->leavesEarly($node, $depth)) {
                    return true;
                }

                continue;
            }

            if (! $node instanceof Node || $node instanceof FunctionLike || $node instanceof ClassLike) {
                continue;
            }

            if ($node instanceof Break_ || $node instanceof Continue_) {
                if (($node->num instanceof Int_ ? $node->num->value : 1) > $depth) {
                    return true;
                }

                continue;
            }

            $inner = $node instanceof For_ || $node instanceof Foreach_ || $node instanceof While_ || $node instanceof Do_
                || $node instanceof Switch_ ? $depth + 1 : $depth;

            foreach ($node->getSubNodeNames() as $name) {
                $child = $node->{$name};

                if (($child instanceof Node || is_array($child)) && $this->leavesEarly($child, $inner)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * What a condition proves of variables when it holds, or fails: each `instanceof` test on a variable that must then
     * pass, through `!`, an `&&` that holds and an `||` that fails, or one `||` chain of tests on one variable that
     * holds, with the classes and the test.
     *
     * @return InstanceofProofs
     */
    private function instanceofProofs(Expr $cond, bool $holds): array
    {
        if ($cond instanceof BooleanNot) {
            return $this->instanceofProofs($cond->expr, ! $holds);
        }

        if (($holds && $cond instanceof BooleanAnd) || (! $holds && $cond instanceof BooleanOr)) {
            return [...$this->instanceofProofs($cond->left, $holds), ...$this->instanceofProofs($cond->right, $holds)];
        }

        $proof = $holds ? $this->instanceofProof($cond) : null;

        return $proof !== null && $proof[0] instanceof Variable && is_string($proof[0]->name)
            ? [[$proof[0]->name, $proof[1], $cond]]
            : [];
    }

    /**
     * Bind what each `if` chain of one block proves, then read every block its statements hold, never a closure's body.
     * $end is the offset the block ends at, or null for a whole body.
     *
     * @param  array<Node\Stmt>  $stmts
     * @param  VariableWrites  $writes
     */
    private function bindBlockGuards(array $stmts, ?int $end, AnalysisScope $scope, array $writes): void
    {
        foreach ($stmts as $stmt) {
            if ($stmt instanceof If_) {
                $this->bindIfGuards($stmt, $end, $scope, $writes);
            }

            foreach ($this->innerBlocks($stmt) as $block) {
                if ($block !== []) {
                    $this->bindBlockGuards($block, $block[array_key_last($block)]->getEndFilePos(), $scope, $writes);
                }
            }
        }
    }

    /**
     * Bind what one `if` chain proves, as proveIfGuards() proves reads non-null: each branch's tests in its block, the
     * failed tests of earlier branches in later ones and the `else`, and those of the leading branches that always
     * leave for the statements after the chain, up to $end.
     *
     * @param  VariableWrites  $writes
     */
    private function bindIfGuards(If_ $if, ?int $end, AnalysisScope $scope, array $writes): void
    {
        $failed = [];
        $leaving = true;
        $exits = [];
        $pastChain = [];

        foreach ([$if, ...$if->elseifs] as $branch) {
            $this->bindBranch([...$failed, ...$this->instanceofProofs($branch->cond, true)], $branch->stmts, $scope, $writes);
            $failed = [...$failed, ...$this->instanceofProofs($branch->cond, false)];
            $last = $branch->stmts === [] ? null : $branch->stmts[array_key_last($branch->stmts)];
            // A whole body's branch must leave the function; a nested block's may also jump out of its loop or switch.
            $leaving = $leaving && ($end === null
                ? $this->alwaysExits($branch->stmts)
                : $last !== null && $this->leavesBlock($last));

            if ($leaving) {
                $exits[] = $branch->stmts;
                $pastChain = $failed;
            }
        }

        if ($if->else !== null) {
            $this->bindBranch($failed, $if->else->stmts, $scope, $writes);
        }

        foreach ($pastChain as [$name, $classes, $test]) {
            // A body that always leaves never reaches the statements past the chain, so neither does a write inside it.
            if (! $this->writtenWithin($name, $test->getStartFilePos(), $end, $exits, $writes)) {
                $this->bindGuard($scope, $name, $classes, $if->getEndFilePos(), $end);
            }
        }
    }

    /**
     * Bind the proofs a branch's block runs under, for its statements, of each variable nothing writes from its test to
     * the block's end; a loop re-tests before it runs the block again.
     *
     * @param  InstanceofProofs  $proofs
     * @param  array<Node\Stmt>  $block
     * @param  VariableWrites  $writes
     */
    private function bindBranch(array $proofs, array $block, AnalysisScope $scope, array $writes): void
    {
        if ($block === []) {
            return;
        }

        $before = $block[array_key_last($block)]->getEndFilePos();

        foreach ($proofs as [$name, $classes, $test]) {
            if (! $this->writtenWithin($name, $test->getStartFilePos(), $before, [], $writes)) {
                $this->bindGuard($scope, $name, $classes, $block[0]->getStartFilePos() - 1, $before);
            }
        }
    }

    /**
     * Bind a variable to the classes a test proves for the reads past $after and up to $before; inside another proof of
     * the variable, to what both prove.
     *
     * @param  non-empty-list<class-string>  $classes
     */
    private function bindGuard(AnalysisScope $scope, string $name, array $classes, int $after, ?int $before): void
    {
        $around = $scope->guardAt(new Variable($name, ['startFilePos' => $after + 1]));

        $scope->varGuardBindings[$name][] = [
            'classes' => $around === null ? $classes : resolve(ReceiverClassResolver::class)->provenClasses($around['classes'], $classes),
            'after' => $after,
            'before' => $before,
        ];
    }

    /**
     * Whether a nested block's last statement never runs on into the statement after the block: it returns, throws,
     * exits, or jumps out of the loop or `switch` around it.
     */
    private function leavesBlock(Node\Stmt $stmt): bool
    {
        return $stmt instanceof Return_
            || $stmt instanceof Break_
            || $stmt instanceof Continue_
            || ($stmt instanceof ExpressionStmt && ($stmt->expr instanceof Throw_ || $stmt->expr instanceof Exit_));
    }

    /**
     * Prove what a body's guards show, once every name it writes has lost the proofs it came in with: a closure's or a
     * called method's body may rewrite a read its caller's guard tested.
     *
     * @param  array<Node\Stmt>  $stmts
     * @param  VariableWrites  $writes
     */
    private function proveGuardedReads(array $stmts, AnalysisScope $scope, array $writes): void
    {
        foreach ($writes as [$name]) {
            unset($scope->nonNullReads[$name]);
        }

        $this->proveBlockGuards($stmts, null, $scope, $writes);
    }

    /**
     * Prove what each `if` of one block shows, then read every block its statements hold, never a closure's body. $end
     * is the offset the block ends at, or null for a whole body.
     *
     * @param  array<Node\Stmt>  $stmts
     * @param  VariableWrites  $writes
     */
    private function proveBlockGuards(array $stmts, ?int $end, AnalysisScope $scope, array $writes): void
    {
        foreach ($stmts as $stmt) {
            if ($stmt instanceof If_) {
                $this->proveIfGuards($stmt, $end, $scope, $writes);
            }

            foreach ($this->innerBlocks($stmt) as $block) {
                if ($block !== []) {
                    $this->proveBlockGuards($block, $block[array_key_last($block)]->getEndFilePos(), $scope, $writes);
                }
            }
        }
    }

    /**
     * Prove what one `if` chain shows: each branch's block runs where its condition holds and every earlier one failed,
     * the `else` where all failed, and the statements after the chain, up to $end, where the conditions of the leading
     * branches that always exit failed.
     *
     * @param  VariableWrites  $writes
     */
    private function proveIfGuards(If_ $if, ?int $end, AnalysisScope $scope, array $writes): void
    {
        $failed = [];
        $exiting = true;
        $exits = [];
        $pastChain = [];

        foreach ([$if, ...$if->elseifs] as $branch) {
            $this->proveBlock([...$failed, ...$this->nonNullReads($branch->cond, true)], $branch->stmts, $if, $scope, $writes);
            $failed = [...$failed, ...$this->nonNullReads($branch->cond, false)];
            $exiting = $exiting && $this->alwaysExits($branch->stmts);

            if ($exiting) {
                $exits[] = $branch->stmts;
                $pastChain = $failed;
            }
        }

        if ($if->else !== null) {
            $this->proveBlock($failed, $if->else->stmts, $if, $scope, $writes);
        }

        foreach ($pastChain as $read) {
            $root = $this->readRoot($read);

            // A body that always exits never reaches the statements after the chain, so neither does a write inside it.
            if ($root !== null && ! $this->writtenWithin($root, $if->cond->getStartFilePos(), $end, $exits, $writes)) {
                $this->proveNonNull([$read], $scope, $if->getEndFilePos(), $end);
            }
        }
    }

    /**
     * Prove reads for the statements of one block an `if` runs, unless a write inside the `if` can change the read.
     *
     * @param  list<Expr>  $reads
     * @param  array<Node\Stmt>  $block
     * @param  VariableWrites  $writes
     */
    private function proveBlock(array $reads, array $block, If_ $if, AnalysisScope $scope, array $writes): void
    {
        if ($block === []) {
            return;
        }

        $after = $block[0]->getStartFilePos() - 1;
        $before = $block[array_key_last($block)]->getEndFilePos() + 1;

        foreach ($reads as $read) {
            $root = $this->readRoot($read);

            if ($root !== null && ! $this->writtenWithin($root, $if->getStartFilePos(), $if->getEndFilePos(), [], $writes)) {
                $this->proveNonNull([$read], $scope, $after, $before);
            }
        }
    }

    /**
     * The statement blocks a statement holds: its branches, a loop's or a bare block's body, a `try`'s blocks and a
     * `switch`'s cases.
     *
     * @return array<array<Node\Stmt>>
     */
    private function innerBlocks(Node\Stmt $stmt): array
    {
        return match (true) {
            $stmt instanceof If_ => [
                $stmt->stmts,
                ...array_map(fn (ElseIf_ $elseif): array => $elseif->stmts, $stmt->elseifs),
                ...($stmt->else === null ? [] : [$stmt->else->stmts]),
            ],
            $stmt instanceof Foreach_, $stmt instanceof For_, $stmt instanceof While_, $stmt instanceof Do_,
            $stmt instanceof Block => [$stmt->stmts],
            $stmt instanceof TryCatch => [
                $stmt->stmts,
                ...array_map(fn (Catch_ $catch): array => $catch->stmts, $stmt->catches),
                ...($stmt->finally === null ? [] : [$stmt->finally->stmts]),
            ],
            $stmt instanceof Switch_ => array_map(fn (Case_ $case): array => $case->stmts, $stmt->cases),
            default => [],
        };
    }

    /**
     * Whether a write to the variable can land between two offsets: one that does not end before $from, nor start past
     * $to when it is set, and that sits in none of $exits, bodies that always exit before the reads it guards.
     *
     * @param  list<array<Node\Stmt>>  $exits
     * @param  VariableWrites  $writes
     */
    private function writtenWithin(string $name, int $from, ?int $to, array $exits, array $writes): bool
    {
        foreach ($this->writesFrom($name, $from, $writes) as $node) {
            $inExit = array_any($exits, fn (array $body): bool => $body !== []
                && $node->getStartFilePos() >= $body[0]->getStartFilePos()
                && $node->getEndFilePos() <= $body[array_key_last($body)]->getEndFilePos());

            if (($to === null || $node->getStartFilePos() <= $to) && ! $inExit) {
                return true;
            }
        }

        return false;
    }
}
