<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use Closure;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BinaryOp\BooleanAnd;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\NotEqual;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Empty_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\Isset_;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;

/**
 * The read paths a guard proves non-null, kept on AnalysisScope::$nonNullReads, and whether a proof holds for a read.
 *
 * @phpstan-import-type NonNullReadsMap from AnalysisScope
 *
 * @internal
 */
trait ReadsNonNullGuards
{
    use ReadsInstanceofChains;

    /**
     * The read paths a condition proves non-null when it evaluates to $holds: a truthy read, a comparison with `null`,
     * `is_null()`, `isset()`, `empty()` and `instanceof`, through `!`, an `&&` that holds and an `||` that fails.
     *
     * @return list<Expr>
     */
    protected function nonNullReads(Expr $condition, bool $holds): array
    {
        if ($condition instanceof BooleanNot) {
            return $this->nonNullReads($condition->expr, ! $holds);
        }

        // Both operands of an `&&` that holds, or of an `||` that fails, evaluate the way the whole condition does.
        if (($holds && $condition instanceof BooleanAnd) || (! $holds && $condition instanceof BooleanOr)) {
            return [...$this->nonNullReads($condition->left, $holds), ...$this->nonNullReads($condition->right, $holds)];
        }

        $reads = match (true) {
            $condition instanceof Isset_ => $holds ? $condition->vars : [],
            $condition instanceof Empty_ => $holds ? [] : [$condition->expr],
            $condition instanceof Instanceof_ => $holds ? [$condition->expr] : [],
            $condition instanceof NotIdentical, $condition instanceof NotEqual => $holds ? $this->comparedWithNull($condition) : [],
            $condition instanceof Identical, $condition instanceof Equal => $holds ? [] : $this->comparedWithNull($condition),
            $condition instanceof FuncCall => $holds ? [] : $this->isNullArgument($condition),
            default => $holds ? [$condition] : [],
        };

        return array_values(array_filter($reads, fn (Expr $read): bool => $this->readRoot($read) !== null));
    }

    /**
     * Prove each read path non-null for the reads past $after, or for every read when it is null. The caller restores
     * the table.
     *
     * @param  list<Expr>  $reads
     */
    protected function proveNonNull(array $reads, AnalysisScope $scope, ?int $after = null): void
    {
        foreach ($reads as $read) {
            $root = $this->readRoot($read);

            if ($root !== null) {
                $scope->nonNullReads[$root][] = ['read' => $read, 'after' => $after];
            }
        }
    }

    /**
     * Resolve with each read path proven non-null, then restore the table.
     *
     * @template TResult
     *
     * @param  list<Expr>  $reads
     * @param  Closure(): TResult  $resolve
     * @return TResult
     */
    protected function resolveProvenNonNull(array $reads, AnalysisScope $scope, Closure $resolve): mixed
    {
        $previous = $scope->nonNullReads;

        try {
            $this->proveNonNull($reads, $scope);

            return $resolve();
        } finally {
            $scope->nonNullReads = $previous;
        }
    }

    /**
     * The proofs that hold in another method of the subject, called where they hold: those of `$this` reads, since it
     * reads the same object, but none of the caller's variables and none bound to an offset in the caller's body.
     *
     * @return NonNullReadsMap
     */
    protected function proofsAcrossCall(AnalysisScope $scope): array
    {
        $proofs = array_values(array_filter($scope->nonNullReads['this'] ?? [], fn (array $proof): bool => $proof['after'] === null));

        return $proofs === [] ? [] : ['this' => $proofs];
    }

    /**
     * Whether a guard in scope proves a read non-null where the read sits.
     */
    protected function isProvenNonNull(Expr $read, AnalysisScope $scope): bool
    {
        $root = $this->readRoot($read);

        return $root !== null && array_any(
            $scope->nonNullReads[$root] ?? [],
            fn (array $proof): bool => ($proof['after'] === null || $read->getStartFilePos() > $proof['after'])
                && $this->isSameReadPath($proof['read'], $read),
        );
    }

    /**
     * The variable a read path starts at, or null for an expression that is no variable or property read.
     */
    protected function readRoot(Expr $expr): ?string
    {
        if ($expr instanceof PropertyFetch || $expr instanceof NullsafePropertyFetch) {
            return $expr->name instanceof Identifier ? $this->readRoot($expr->var) : null;
        }

        return $expr instanceof Variable && is_string($expr->name) ? $expr->name : null;
    }

    /**
     * The operand a comparison tests against the `null` constant, or none.
     *
     * @return list<Expr>
     */
    private function comparedWithNull(BinaryOp $comparison): array
    {
        $isNull = fn (Expr $operand): bool => $operand instanceof ConstFetch && $operand->name->toLowerString() === 'null';

        return match (true) {
            $isNull($comparison->right) => [$comparison->left],
            $isNull($comparison->left) => [$comparison->right],
            default => [],
        };
    }

    /**
     * The value an `is_null()` call tests, or none for any other call.
     *
     * @return list<Expr>
     */
    private function isNullArgument(FuncCall $call): array
    {
        $argument = $call->args[0] ?? null;

        return $call->name instanceof Name && $call->name->toLowerString() === 'is_null' && $argument instanceof Arg && ! $argument->unpack
            ? [$argument->value]
            : [];
    }
}
