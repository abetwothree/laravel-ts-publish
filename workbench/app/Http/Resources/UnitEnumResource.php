<?php

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Visibility;

/**
 * Resource wrapping a unit enum (no backing type) to test the ->value fallback.
 * Also accesses an unknown property to test the unknown enum property path.
 */
class UnitEnumResource extends JsonResource
{
    /** @var Visibility|null */
    public $resource;

    public static $wrap = '';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->resource->name,
            'value' => $this->resource->value,
            'custom' => $this->resource->someUnknownProp,
        ];
    }
}
