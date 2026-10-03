<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Facility;

/**
 * Reads a relation whose model is published on demand, and one whose model is never published.
 *
 * @mixin Facility
 */
class FacilityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'audit_trails' => $this->auditTrails,
            'latest_trail' => $this->auditTrails->first(),
            'excluded_records' => $this->excludedRecords,
            'first_excluded' => $this->excludedRecords->first(),
            'summary' => ['trails' => $this->auditTrails, 'excluded' => $this->excludedRecords],
        ];
    }
}
