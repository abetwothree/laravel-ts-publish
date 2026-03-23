<?php

namespace Workbench\Accounting\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\Accounting\Models\Invoice;

/**
 * Exercises: when(cond, EnumResource::make) — conditional enum, cross-module
 * whenLoaded bare (App\User), Resource::collection sibling, whenCounted,
 * when(cond, value), mergeWhen.
 *
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->when($this->status?->value !== 'draft', EnumResource::make($this->status)),
            'subtotal' => $this->subtotal,
            'tax' => $this->tax,
            'total' => $this->total,
            'due_at' => $this->due_at,
            'issued_at' => $this->whenNotNull($this->issued_at),
            'paid_at' => $this->when($this->paid_at !== null, $this->paid_at),
            'user' => $this->whenLoaded('user'),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'payments_count' => $this->whenCounted('payments'),
            $this->mergeWhen($this->status?->value === 'paid', [
                'notes' => $this->notes,
            ]),
        ];
    }
}
