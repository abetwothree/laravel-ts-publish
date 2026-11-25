<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Arrays\Read;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use Closure;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\BinaryOp\Plus;
use PhpParser\Node\Expr\Variable;

/**
 * Classifies a whole array base without analyzing it, so the gate that admits a base and the reader that reads it read
 * one answer.
 *
 * @internal
 */
final class BaseForms
{
    /**
     * The form of a base: a literal or a `+` by syntax, none for a local whose read is under way, which would read
     * itself without end, and any other form as the host reads it.
     *
     * @param  Closure(Expr): ?BaseForm  $host  the forms only the host classifies: a local, a merge, a host base
     */
    public static function classify(Expr $expr, AnalysisScope $scope, Closure $host): ?BaseForm
    {
        return match (true) {
            $expr instanceof Array_ => BaseForm::Literal,
            $expr instanceof Plus => BaseForm::Plus,
            $expr instanceof Variable && (! is_string($expr->name) || isset($scope->readingLocals[$expr->name])) => null,
            default => $host($expr),
        };
    }
}
