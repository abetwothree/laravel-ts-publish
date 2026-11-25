<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns\Bodies;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Read\BaseForm;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Read\BaseForms;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\BinaryOp\Plus;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;

/**
 * Reads a whole array a method returns or builds a variable from, and gates that read by syntax alone: a literal, a
 * local whose read sees one write of a base, a `+` of bases, or a base only the host reads (a `parent::` call, an
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
     * The analysis of a whole array a method returns or builds a variable from: a literal, a local whose read sees one
     * write of a base, a `+` of bases, or a base the host reads; null for any other expression.
     *
     * @return TAnalysis|null
     */
    protected function analyzeArrayExpression(Expr $expr, bool $topLevel = true): ?MethodAnalysis
    {
        return match ($this->baseForm($expr, $local)) {
            BaseForm::Literal => $expr instanceof Array_ ? $this->arrayLiteralAnalysis($expr, $topLevel) : null,
            BaseForm::Local => $local === null ? null : $this->analysisScope()->readingLocal(
                $local[0],
                fn (): ?MethodAnalysis => $this->analyzeArrayExpression($local[1], $topLevel),
            ),
            BaseForm::Plus => $expr instanceof Plus ? $this->plusBase($expr, $topLevel) : null,
            BaseForm::Merge, BaseForm::Host => $this->hostArrayBase($expr, $topLevel),
            null => null,
        };
    }

    /**
     * Whether analyzeArrayExpression() reads an expression, decided without analyzing it: a gate that analyzed a write
     * the walk then analyzes again would count its dropped union arms twice.
     */
    protected function readsAsArray(Expr $expr): bool
    {
        return match ($this->baseForm($expr, $local)) {
            BaseForm::Plus => $expr instanceof Plus && $this->readsAsArray($expr->left) && $this->readsAsArray($expr->right),
            BaseForm::Local => $local !== null
                && $this->analysisScope()->readingLocal($local[0], fn (): bool => $this->readsAsArray($local[1])),
            BaseForm::Literal, BaseForm::Merge, BaseForm::Host => true,
            null => false,
        };
    }

    /**
     * A local variable's name and the value of the one write its read sees; null for any other expression. Only
     * baseForm() asks, after BaseForms::classify() has declined a local whose read is under way.
     *
     * @return WrittenLocal|null
     */
    private function writtenLocal(Expr $expr): ?array
    {
        if (! $expr instanceof Variable || ! is_string($expr->name)) {
            return null;
        }

        $bound = $this->soleSpelledWrite($expr, $this->analysisScope());

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

    /**
     * The one classification the gate and the reader read: a literal or a `+` by syntax, a local through its one
     * write, and a merge or a host base as the host's gate admits it.
     *
     * @param  WrittenLocal|null  $local  set to the local a `Local` base names
     */
    private function baseForm(Expr $expr, ?array &$local): ?BaseForm
    {
        $local = null;

        return BaseForms::classify($expr, $this->analysisScope(), function (Expr $base) use (&$local): ?BaseForm {
            $local = $this->writtenLocal($base);

            return match (true) {
                $local !== null => BaseForm::Local,
                ! $this->hostReadsArrayBase($base) => null,
                default => $base instanceof FuncCall ? BaseForm::Merge : BaseForm::Host,
            };
        });
    }

    /**
     * `$a + $b`, which keeps every key $a sets, as `+=` does; null unless both sides read.
     *
     * @return TAnalysis|null
     */
    private function plusBase(Plus $expr, bool $topLevel): ?MethodAnalysis
    {
        $left = $this->readsAsArray($expr) ? $this->analyzeArrayExpression($expr->left, $topLevel) : null;
        $right = $left === null ? null : $this->analyzeArrayExpression($expr->right, $topLevel);

        if ($left === null || $right === null) {
            return null;
        }

        $right->dropKeysHeldBy($left);
        $left->merge($right);

        return $left;
    }
}
