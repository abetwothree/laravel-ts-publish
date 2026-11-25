<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns\Bodies;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\BinaryOp\Plus;
use PhpParser\Node\Expr\Variable;

/**
 * Reads a whole array a method returns or builds a variable from, and gates that read by syntax alone: a literal, a
 * local written once with a base, a `+` of bases, or a base only the host reads (a `parent::` call, an
 * `array_merge()`, a model filter, a `$this->` helper), for the returned-variable walk, a method's return and a merge.
 *
 * @template TAnalysis of MethodAnalysis
 *
 * @phpstan-type WrittenLocal array{string, Expr}
 *
 * @internal
 */
trait ReadsArrayBases
{
    use InspectsAstNodes;

    /**
     * The scope the base is read in.
     */
    abstract protected function analysisScope(): AnalysisScope;

    /**
     * The analysis of one array literal read as a whole base.
     *
     * @return TAnalysis
     */
    abstract protected function arrayLiteralAnalysis(Array_ $array, bool $topLevel): MethodAnalysis;

    /**
     * The analysis of a base only the host reads, or null for an expression it does not read.
     *
     * @return TAnalysis|null
     */
    abstract protected function hostArrayBase(Expr $expr, bool $topLevel): ?MethodAnalysis;

    /**
     * Whether hostArrayBase() reads an expression, decided without analyzing it.
     */
    abstract protected function hostReadsArrayBase(Expr $expr): bool;

    /**
     * The analysis of a whole array a method returns or builds a variable from: a literal, a local written once with a
     * base, a `+` of bases, or a base the host reads; null for any other expression.
     *
     * @return TAnalysis|null
     */
    protected function analyzeArrayExpression(Expr $expr, bool $topLevel = true): ?MethodAnalysis
    {
        if ($expr instanceof Array_) {
            return $this->arrayLiteralAnalysis($expr, $topLevel);
        }

        $local = $this->writtenLocal($expr);

        if ($local !== null) {
            return $this->analysisScope()->readingLocal(
                $local[0],
                fn (): ?MethodAnalysis => $this->analyzeArrayExpression($local[1], $topLevel),
            );
        }

        // `$a + $b` keeps every key $a sets, as `+=` does.
        if ($expr instanceof Plus) {
            $left = $this->readsAsArray($expr) ? $this->analyzeArrayExpression($expr->left, $topLevel) : null;
            $right = $left === null ? null : $this->analyzeArrayExpression($expr->right, $topLevel);

            if ($left === null || $right === null) {
                return null;
            }

            $right->dropKeysHeldBy($left);
            $left->merge($right);

            return $left;
        }

        return $this->hostArrayBase($expr, $topLevel);
    }

    /**
     * Whether analyzeArrayExpression() reads an expression, decided without analyzing it: a gate that analyzed a write
     * the walk then analyzes again would count its dropped union arms twice.
     */
    protected function readsAsArray(Expr $expr): bool
    {
        if ($expr instanceof Plus) {
            return $this->readsAsArray($expr->left) && $this->readsAsArray($expr->right);
        }

        $local = $this->writtenLocal($expr);

        if ($local !== null) {
            return $this->analysisScope()->readingLocal($local[0], fn (): bool => $this->readsAsArray($local[1]));
        }

        return $expr instanceof Array_ || $this->hostReadsArrayBase($expr);
    }

    /**
     * A local variable's name and the value of the one write its read sees, unless its read is under way; null for any
     * other expression.
     *
     * @return WrittenLocal|null
     */
    private function writtenLocal(Expr $expr): ?array
    {
        $scope = $this->analysisScope();

        if (! $expr instanceof Variable || ! is_string($expr->name) || isset($scope->readingLocals[$expr->name])) {
            return null;
        }

        $bound = $this->soleSpelledWrite($expr, $scope);

        return $bound === null ? null : [$expr->name, $bound];
    }

    /**
     * The keys one merged array literal sets, each required within its own branch.
     *
     * @return TAnalysis
     */
    private function mergedArrayAnalysis(Array_ $array): MethodAnalysis
    {
        return $this->arrayLiteralAnalysis($array, false);
    }
}
