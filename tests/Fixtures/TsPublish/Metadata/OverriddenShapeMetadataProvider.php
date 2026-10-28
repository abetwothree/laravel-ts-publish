<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Packages\Ui\Chart;
use Workbench\App\Packages\Ui\Gauge;
use Workbench\App\Packages\Ui\Panel;

final class OverriddenShapeMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide metadata whose docblock shape names overridden classes, at its top level and nested, beside one a cast
     * replaces.
     *
     * @return array{chart: Chart, nested: array{panel: Panel, n: int}, label: string, gauge: Gauge}
     */
    #[TsCasts(['gauge' => 'string'])]
    public function provide(Model $model): array
    {
        return ['chart' => new Chart, 'nested' => ['panel' => new Panel, 'n' => 1], 'label' => $model->getTable(), 'gauge' => 'level'];
    }
}
