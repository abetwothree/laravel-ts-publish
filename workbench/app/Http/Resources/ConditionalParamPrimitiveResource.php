<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Order;

/**
 * Exercises issue #38: closure parameter passed by the conditional method.
 * Each field uses a single-param arrow function that returns a scalar primitive.
 *
 * The bug: the analyzer resolves the return type of these closures as `unknown`
 * instead of inferring the scalar type from the return expression.
 *
 * @mixin Order
 */
class ConditionalParamPrimitiveResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // Arrow fn param → string property
            'user_name' => $this->whenLoaded('user', fn ($user) => $user->name),

            // Arrow fn param → int property
            'user_id' => $this->whenLoaded('user', fn ($user) => $user->id),

            // Arrow fn param → bool cast expression
            'user_verified' => $this->whenLoaded('user', fn ($user) => (bool) $user->email_verified_at),

            // when() with arrow fn receiving the truthy value as param
            'notes_upper' => $this->when($this->notes, fn ($notes) => strtoupper($notes)),

            // when() with arrow fn returning a plain int literal
            'notes_length' => $this->when($this->notes !== null, fn ($notes) => strlen($this->notes ?? '')),
        ];
    }
}
