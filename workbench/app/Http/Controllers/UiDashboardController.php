<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Packages\Ui\Chart;
use Workbench\App\Packages\Ui\Gauge;
use Workbench\App\Packages\Ui\Panel;
use Workbench\App\Packages\Ui\Pennies;

/** Renders props that are values of a class with one inherited override and of a composed class. */
class UiDashboardController
{
    public function index(Request $request): Response
    {
        // #136's example, a nullable and a collected one, then a composed, an own and an inherited override.
        return Inertia::render('Ui/Dashboard', [
            'chart' => new Panel,
            'widgets' => [new Panel, new Panel],
            'maybe' => $request->boolean('maybe') ? new Panel : null,
            'coll' => collect([new Panel, new Panel]),
            'composed' => new Chart,
            'gauge' => new Gauge,
            'pennies' => new Pennies(5),
        ]);
    }
}
