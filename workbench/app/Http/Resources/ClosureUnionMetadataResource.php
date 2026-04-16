<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Order;

/**
 * Exercises analyzeClosureUnion metadata propagation (enum, model, resource FQCNs)
 * and analyzeRelatedModelMethodCall fallback (line 451).
 *
 * @mixin Order
 */
class ClosureUnionMetadataResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // Closure union with direct enum return — exercises directEnumFqcn (line 1938)
            'status_or_null' => $this->whenLoaded('user', function () {
                if ($this->user) {
                    return $this->status;
                }

                return null;
            }),

            // Closure union with resource return — exercises resourceFqcn (line 1954)
            'nested_or_null' => $this->whenLoaded('user', function () {
                if ($this->user) {
                    return TagResource::make($this->user);
                }

                return null;
            }),

            // Related model method call inside whenLoaded — exercises line 451
            'user_titled' => $this->whenLoaded('user', function () {
                return $this->user->nameTitled();
            }),

            // Closure union with inline array containing nested resource — exercises
            // embeddedResourceFqcns propagation (lines 1950, 1988)
            'detail_or_null' => $this->whenLoaded('user', function () {
                if ($this->user) {
                    return [
                        'tag' => TagResource::make($this->user),
                        'name' => $this->user->name,
                    ];
                }

                return null;
            }),

            // Closure union where one branch returns a whenLoaded (model FQCN) — exercises
            // modelFqcn propagation in analyzeClosureUnion (line 1958)
            'items_or_null' => $this->whenLoaded('user', function () {
                if ($this->user) {
                    return $this->whenLoaded('items');
                }

                return null;
            }),
        ];
    }
}
