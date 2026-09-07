<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\InertiaResourcePropHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ModelFinderHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\StaticCallHandler;

/**
 * The controller profile: the generic resource profile plus the two handlers that only make sense
 * where a value is a whole HTTP payload rather than a member of one.
 *
 * @internal
 */
final class ControllerExpressionHandlers
{
    /**
     * Build the ordered controller handler chain.
     *
     * Both additions sit immediately before StaticCallHandler, whose final arm claims every
     * StaticCall and never declines — after it they would be unreachable.
     *
     * @return list<ExpressionHandler>
     */
    public static function make(): array
    {
        $handlers = [];

        foreach (ResourceExpressionHandlers::generic() as $handler) {
            if ($handler instanceof StaticCallHandler) {
                $handlers[] = new ModelFinderHandler;
                $handlers[] = new InertiaResourcePropHandler;
            }

            $handlers[] = $handler;
        }

        return $handlers;
    }
}
