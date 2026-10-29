<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Packages\Ui\Chart;

/**
 * Provides a composed class's value beside a cast importing its `ChartType` name from another module.
 */
final class UiChartCastMetadataProvider implements ModelMetadataProvider
{
    /**
     * The metadata every model's companion carries.
     *
     * @return array<string, mixed>
     */
    #[TsCasts(['other' => ['type' => 'ChartType', 'import' => '@js/types/ui']])]
    public function provide(Model $model): array
    {
        return ['chart' => $this->chart(), 'other' => 'bar'];
    }

    /**
     * A chart, by its declared class.
     */
    private function chart(): Chart
    {
        return new Chart;
    }
}
