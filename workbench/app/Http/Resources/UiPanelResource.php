<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\UiTone;
use Workbench\App\Packages\Ui\Chart;
use Workbench\App\Packages\Ui\Dial;
use Workbench\App\Packages\Ui\Panel;

/**
 * Fixture: values of overridden classes, through declared helpers and through `new`, so the file imports each override
 * and each composed class's `…Type` from its module; an overridden enum's case, and its `EnumResource`, which keeps the
 * enum's own name.
 */
class UiPanelResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'chart' => $this->chart(),
            'dial' => $this->dial($request->boolean('dial')),
            'panel' => new Panel,
            'panels' => [new Panel, new Panel],
            'when_panel' => $this->when($request->boolean('panel'), new Panel),
            'maybe_chart' => $request->boolean('chart') ? new Chart : null,
            'tone' => UiTone::Bright,
            'tone_resource' => EnumResource::make(UiTone::Bright),
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
     * A dial when asked for one.
     */
    protected function dial(bool $flag): ?Dial
    {
        return $flag ? new Dial : null;
    }
}
