<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Packages\Ui\Chart;
use Workbench\App\Packages\Ui\Dial;

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
