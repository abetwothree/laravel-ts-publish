<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Contracts;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Variants\ConditionalArms;
use PhpParser\Node\Expr;

/**
 * A handler whose expression picks one of several values by a condition, which it hands over arm by arm, each typed by
 * the same code that types the whole expression.
 *
 * @internal
 */
interface SplitsOnCondition
{
    /**
     * The arms a claimed expression picks between, in the order PHP tries them; null for a shape it does not split.
     */
    public function conditionalArms(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?ConditionalArms;
}
