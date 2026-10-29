<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\Chart as ChartEnum;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Packages\Ui\Chart;

class UiChartKindPageController
{
    /**
     * A page whose props are a composed class's `ChartType` and an enum whose type is also `ChartType`.
     */
    public function show(): Response
    {
        return Inertia::render('Ui/ChartKind', ['chart' => $this->chart(), 'kind' => $this->kind()]);
    }

    /**
     * A chart, by its declared class.
     */
    protected function chart(): Chart
    {
        return new Chart;
    }

    /**
     * The chart's kind, by its declared enum.
     */
    protected function kind(): ChartEnum
    {
        return ChartEnum::Bar;
    }
}
