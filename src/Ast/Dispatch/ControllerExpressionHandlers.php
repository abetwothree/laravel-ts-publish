<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Dispatch;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;

/**
 * The controller profile: the Inertia profile a page's props run, the one a share middleware's values run.
 *
 * @internal
 */
final class ControllerExpressionHandlers
{
    /**
     * Build the ordered handler chain an Inertia action's props run.
     *
     * @return list<ExpressionHandler>
     */
    public static function make(): array
    {
        return ResourceExpressionHandlers::forInertia(ResourceExpressionHandlers::forNonResourceSubjects());
    }
}
