<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Packages\Ui\Chart;

/**
 * Provides a composed class's value beside a cast importing a later name from the same module path.
 */
final class UiChartZoneMetadataProvider implements ModelMetadataProvider
{
    /**
     * The metadata every model's companion carries.
     *
     * @return array<string, mixed>
     */
    #[TsCasts(['zone' => ['type' => 'ZoneType', 'import' => '../packages/ui']])]
    public function provide(Model $model): array
    {
        return ['chart' => $this->chart(), 'zone' => 'north'];
    }

    /**
     * A chart, by its declared class.
     */
    private function chart(): Chart
    {
        return new Chart;
    }
}
