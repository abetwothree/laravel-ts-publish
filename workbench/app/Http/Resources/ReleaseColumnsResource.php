<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Release;

/**
 * Filters written inside the model itself: a method body the resource forwards to, and an accessor it reads.
 *
 * @mixin Release
 */
final class ReleaseColumnsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'columns' => $this->columnSummary(),
            'picks' => $this->column_picks,
        ];
    }
}
