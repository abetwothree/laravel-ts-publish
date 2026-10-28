<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Packages\Ui\Chart;
use Workbench\App\Packages\Ui\Dial;
use Workbench\App\Packages\Ui\Panel;
use Workbench\App\Packages\Ui\Plain;

/**
 * Fixture: values of composed classes through declared helpers, so the file imports their `…Type` from their module.
 */
class UiPanelResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'chart' => $this->chart(),
            'dial' => $this->dial($request->boolean('dial')),
            // A `new` of an overridden class publishes its override; `plain` has none, so it keeps today's answer.
            'panel' => new Panel,
            'panels' => [new Panel, new Panel],
            'when_panel' => $this->when($request->boolean('panel'), new Panel),
            'maybe_chart' => $request->boolean('chart') ? new Chart : null,
            'plain' => new Plain,
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
