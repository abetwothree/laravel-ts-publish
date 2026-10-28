<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\Chart as ChartEnum;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Packages\Ui\Chart;

/**
 * A test-only event whose payload holds a composed class's `ChartType` beside an enum whose type is also `ChartType`.
 */
final class UiChartKindBroadcast implements ShouldBroadcast
{
    /** Takes the chart and the kind its payload sends. */
    public function __construct(public Chart $chart, public ChartEnum $kind) {}

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('ui-chart-kind');
    }
}
