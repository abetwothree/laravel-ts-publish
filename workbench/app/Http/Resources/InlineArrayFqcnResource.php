<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Order;

/**
 * Exercises analyzeInlineArray embeddedModelFqcns and embeddedResourceFqcns
 * (lines 1501, 1508-1510) by returning inline arrays that contain
 * whenLoaded() (model FQCN) and SomeResource::make() (resource FQCN)
 * inside a closure union.
 *
 * @mixin Order
 */
class InlineArrayFqcnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // Closure union returning inline arrays with model and resource FQCNs
            'payload' => $this->whenLoaded('user', function () {
                if ($this->user) {
                    return [
                        'address' => AddressResource::make($this->user),
                        'items_loaded' => $this->whenLoaded('items'),
                    ];
                }

                return null;
            }),
        ];
    }
}
