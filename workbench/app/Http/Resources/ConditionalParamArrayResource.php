<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Order;

/**
 * Exercises issue #38: closure parameter passed by the conditional method.
 * Each field uses a single-param arrow function that returns an inline array literal.
 *
 * The bug: the analyzer resolves the return type as `unknown` instead of
 * inferring the array shape `{ id: number; email: string; name: string }`.
 *
 * @mixin Order
 */
class ConditionalParamArrayResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // Arrow fn param → inline array literal with multiple scalar keys
            'user_summary' => $this->whenLoaded('user', fn ($user) => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
            ]),

            // when() with arrow fn param → inline array literal
            'shipping_summary' => $this->when(
                $this->shipping_address !== null,
                fn ($address) => [
                    'city' => $address['city'] ?? null,
                    'country' => $address['country_code'] ?? null,
                ]
            ),

            // whenLoaded with arrow fn param → nested array literal
            'user_meta' => $this->whenLoaded('user', fn ($user) => [
                'profile' => [
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'verified' => (bool) $user->email_verified_at,
            ]),
        ];
    }
}
