<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Supplier;

/**
 * The API resource a stacked collection names over the one its parent collects.
 *
 * @mixin Supplier
 */
class StubGizmoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['gizmo_id' => $this->id];
    }
}
