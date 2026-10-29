<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\UiBoard;
use Workbench\App\Packages\Ui\Chart;
use Workbench\App\Packages\Ui\Gauge;
use Workbench\App\Packages\Ui\Panel;
use Workbench\App\Packages\Ui\Pennies;

/** Renders props that are values of a class with one inherited override and of a composed class. */
class UiDashboardController
{
    public function index(Request $request): Response
    {
        // #136's example, a nullable and a collected one, then a composed, an own and an inherited override, two typed
        // closures Inertia sends only when a partial reload asks for them, and a docblock shape and an untyped array.
        $board = new UiBoard;

        return Inertia::render('Ui/Dashboard', [
            'chart' => new Panel,
            'widgets' => [new Panel, new Panel],
            'maybe' => $request->boolean('maybe') ? new Panel : null,
            'coll' => collect([new Panel, new Panel]),
            'composed' => new Chart,
            'gauge' => new Gauge,
            'pennies' => new Pennies(5),
            'deferred' => Inertia::defer(fn (): Gauge => new Gauge),
            'optional' => Inertia::optional(fn (): Chart => new Chart),
            'shaped' => $this->shaped(),
            'untyped' => $this->untyped(),
            'uploader' => $board->uploader_from_docblock,
            'gauge_from_docblock' => $board->gauge_from_docblock,
        ]);
    }

    /**
     * A chart, a panel and their gauges, by a docblock shape.
     *
     * @return array{chart: Chart, panel: Panel, gauges: list<Gauge>}
     */
    protected function shaped(): array
    {
        return ['chart' => new Chart, 'panel' => new Panel, 'gauges' => [new Gauge, new Gauge]];
    }

    /**
     * A panel and a chart, with no declared return type.
     */
    protected function untyped()
    {
        return ['panel' => new Panel, 'chart' => new Chart];
    }
}
