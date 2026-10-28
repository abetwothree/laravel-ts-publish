<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\Chart as ChartEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Packages\Ui\Chart;

/**
 * A composed class's `ChartType` from an accessor beside an enum cast whose type is also `ChartType`; its test creates
 * the `ui_chart_kind_holders` table.
 */
class UiChartKindHolder extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $appends = ['chart'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => ChartEnum::class];
    }

    /**
     * A chart, by a getter closure that declares its class.
     */
    protected function chart(): Attribute
    {
        return Attribute::make(get: fn (): Chart => new Chart);
    }
}
