<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\MaybeNode;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Packages\Ui\Chart;
use Workbench\App\Packages\Ui\Gauge;
use Workbench\App\Packages\Ui\Panel;
use Workbench\App\Packages\Ui\Plain;

/**
 * Typed closures whose bodies look a value up by a container id, which the engine cannot type, so each closure's
 * declared return type answers: overridden classes, and a plain class that keeps its inferred shape.
 */
class UiTypedClosurePageController
{
    /**
     * Renders each typed closure as a prop.
     */
    public function index(): Response
    {
        return Inertia::render('Ui/TypedClosures', [
            'gauge' => fn (): Gauge => app('ui.gauge'),
            'deferred' => Inertia::defer(fn (): Gauge => app('ui.gauge')),
            'optional' => Inertia::optional(fn (): Chart => app('ui.chart')),
            'panel' => fn (): ?Panel => app('ui.panel'),
            'maybe' => fn (): ?MaybeNode => app('ui.maybe'),
            'plain' => fn (): Plain => app('ui.plain'),
        ]);
    }
}
