<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Spreads;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\Spreads\ArmShelf;

/**
 * An arm holding a number key beside an enum wrap (GH-161 D3).
 *
 * @property-read ArmShelf $resource
 */
class StatusWrapArmResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => 1, 'name' => $this->resource->title, 'status' => EnumResource::make($this->resource->status)];
    }
}
