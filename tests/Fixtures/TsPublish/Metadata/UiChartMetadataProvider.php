<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Packages\Ui\Chart;

/**
 * Provides a value of a composed class, so the companion imports the class's module.
 */
final class UiChartMetadataProvider implements ModelMetadataProvider
{
    /**
     * The metadata every model's companion carries.
     *
     * @return array<string, mixed>
     */
    public function provide(Model $model): array
    {
        return ['chart' => $this->chart()];
    }

    /**
     * A chart, by its declared class.
     */
    private function chart(): Chart
    {
        return new Chart;
    }
}
