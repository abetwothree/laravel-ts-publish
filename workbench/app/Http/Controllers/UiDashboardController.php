<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Packages\Ui\Chart;
use Workbench\App\Packages\Ui\Panel;

/** Renders props that are values of a class with one inherited override and of a composed class. */
class UiDashboardController
{
    public function index(): Response
    {
        return Inertia::render('Ui/Dashboard', [
            'chart' => $this->panel(),
            'composed' => $this->chart(),
        ]);
    }

    /**
     * A panel, by its declared class.
     */
    protected function panel(): Panel
    {
        return new Panel;
    }

    /**
     * A chart, by its declared class.
     */
    protected function chart(): Chart
    {
        return new Chart;
    }
}
