<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Badge;

/**
 * Reads one enum bare and wraps the other, so the file imports Clearance's type and ClearanceType's const: two
 * imports that would both be named `ClearanceType`.
 *
 * @mixin Badge
 */
class BadgeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'clearance' => $this->clearance,
            'clearance_type' => EnumResource::make($this->clearance_type),
            // The same pair inside an inline array: the wrap's const alias must not reach the bare read's type.
            'summary' => [
                'clearance' => $this->clearance,
                'clearance_type' => EnumResource::make($this->clearance_type),
            ],
        ];
    }
}
