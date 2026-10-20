<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Order;

/**
 * Exercises comparison and boolean operator expressions.
 *
 * @mixin Order
 */
class BooleanExprResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'is_recent' => $this->created_at !== null,
            'is_equal' => $this->total === 100,
            'is_large' => $this->total > 100,
            'both' => $this->total > 100 && $this->notes !== null,
            'negated' => ! $this->is_paid,
            'is_order' => $this->resource instanceof Order,
            'has_notes' => isset($this->notes),
            'no_notes' => empty($this->notes),
            'compared' => $this->total <=> 100,
            // Regression guard: (float) cast already resolves to `number` without a #[TsCasts] override.
            'price_float' => (float) $this->total,
            // Regression guard: whenLoaded closure over a nullsafe relation property already
            // resolves to `string | null`, optional, without a #[TsCasts] override. The `?string`
            // return annotation here is inert — resolution comes from the body, `$this->user?->email`,
            // against the loaded `user` relation model, not from the annotation.
            'user_bio' => $this->whenLoaded('user', fn (): ?string => $this->user?->email),
        ];
    }
}
