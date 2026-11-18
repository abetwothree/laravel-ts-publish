<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Variants;

use Closure;
use PhpParser\Node\Expr;

/**
 * The arms a conditional expression picks between, in the order PHP tries their guards.
 *
 * @internal
 */
final readonly class ConditionalArms
{
    /**
     * The arms in the order PHP tries them.
     *
     * @param  list<ConditionalArm>  $arms
     */
    public function __construct(public array $arms) {}

    /**
     * The first arm whose guard is absent or holds; null once a guard before it cannot be decided, or where none holds.
     *
     * @param  Closure(Expr): ?bool  $decide
     */
    public function select(Closure $decide): ?ConditionalArm
    {
        foreach ($this->arms as $arm) {
            $holds = $arm->guard === null ? true : $decide($arm->guard);

            if ($holds === null) {
                return null;
            }

            if ($holds) {
                return $arm;
            }
        }

        return null;
    }
}
