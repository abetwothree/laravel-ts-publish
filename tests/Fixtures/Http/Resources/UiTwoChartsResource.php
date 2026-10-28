<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Clash\Chart as ClashChart;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Packages\Ui\Chart;

/**
 * Publishes two composed classes that share a basename, so both publish `ChartType`.
 */
class UiTwoChartsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['chart' => $this->chart(), 'clash' => $this->clash()];
    }

    /**
     * A chart, by its declared class.
     */
    protected function chart(): Chart
    {
        return new Chart;
    }

    /**
     * A chart of another namespace, by its declared class.
     */
    protected function clash(): ClashChart
    {
        return new ClashChart;
    }
}
