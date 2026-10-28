<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\UiChartKindHolder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Packages\Ui\Chart;

/**
 * Reads, through an untyped helper, an enum whose type is spelled like a composed class's beside that class: the enum
 * rides a channel the body fallback cannot carry, so it is `unknown` though the class's import spells its name.
 */
class UiUntypedChartKindResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['kinds' => $this->kinds()];
    }

    /**
     * The holder's kind beside a chart, with no declared return type.
     */
    protected function kinds()
    {
        return ['kind' => $this->holder()->kind, 'chart' => new Chart];
    }

    /**
     * A holder of a bar chart.
     */
    protected function holder(): UiChartKindHolder
    {
        return new UiChartKindHolder(['kind' => 'bar']);
    }
}
