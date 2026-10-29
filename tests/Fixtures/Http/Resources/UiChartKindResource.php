<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\Chart as ChartEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Packages\Ui\Chart;

/**
 * Publishes a composed class's `ChartType` beside an enum whose type is also `ChartType`.
 */
class UiChartKindResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'chart' => $this->chart(),
            'kind' => $this->kind(),
        ];
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
