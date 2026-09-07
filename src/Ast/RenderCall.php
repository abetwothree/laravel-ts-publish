<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use PhpParser\Node\Expr;

/**
 * One Inertia render call, normalized: the component-name argument and the props argument,
 * whichever of the facade, helper, or helper-chain call forms produced them.
 *
 * @internal
 */
final class RenderCall
{
    public function __construct(
        public readonly ?Expr $nameArg = null,
        public readonly ?Expr $propsArg = null,
    ) {}
}
