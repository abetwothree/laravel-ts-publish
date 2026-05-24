<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Order;
use Workbench\App\Models\OrderItem;

/**
 * Exercises issue #38 using non-arrow (full) closures with a parameter.
 * Covers primitives, arrays, resources, enums, and guard-clause patterns —
 * all using `function ($param) { return ...; }` syntax rather than arrow fns.
 *
 * The bug: the analyzer resolves the return type of these closures as `unknown`
 * regardless of the return expression when a parameter is present.
 *
 * @mixin Order
 */
class ConditionalParamFullClosureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // Full closure param → string primitive
            'user_name' => $this->whenLoaded('user', function ($user) {
                return $user->name;
            }),

            // Full closure param → inline array literal
            'user_summary' => $this->whenLoaded('user', function ($user) {
                return [
                    'id' => $user->id,
                    'email' => $user->email,
                ];
            }),

            // Full closure param → mapped collection (exact bug from issue #38)
            'items_mapped' => $this->whenLoaded('items', function ($items) {
                return $items->map(function (OrderItem $item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'quantity' => $item->quantity,
                    ];
                });
            }),

            // Full closure param → Resource::make
            'user_resource' => $this->whenLoaded('user', function ($user) {
                return UserResource::make($user);
            }),

            // Full closure param → EnumResource::make on enum attribute (value passed to closure by whenNotNull)
            'status_resource' => $this->whenNotNull($this->status, function ($status) {
                return EnumResource::make($status);
            }),

            // Full closure param with guard clause → array literal
            'shipping_safe' => $this->whenLoaded('user', function ($user) {
                if ($user === null) {
                    return null;
                }

                return [
                    'name' => $user->name,
                    'email' => $user->email,
                ];
            }),
        ];
    }
}
