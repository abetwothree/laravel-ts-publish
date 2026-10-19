<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Kpi;

/**
 * Fixture: Kpi::reportable() morphs to two Report models sharing basename and parent segment,
 * reproducing a same-basename alias collision through a resource instead of a model.
 *
 * @mixin Kpi
 */
class KpiResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'reportable' => $this->whenLoaded('reportable'),
        ];
    }
}
