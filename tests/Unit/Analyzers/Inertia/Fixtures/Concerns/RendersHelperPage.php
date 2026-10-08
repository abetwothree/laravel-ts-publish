<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\Concerns;

use Inertia\Response;

/** Supplies a controller's Inertia action from its own file: ControllerWithDelegatedProps::helper(), moved. */
trait RendersHelperPage
{
    /** Render a page with one string prop. */
    public function helper(): Response
    {
        return inertia('Dashboard/Helper', ['label' => 'hi']);
    }
}
