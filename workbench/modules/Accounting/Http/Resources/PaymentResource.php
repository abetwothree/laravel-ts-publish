<?php

declare(strict_types=1);

namespace Workbench\Accounting\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\Accounting\Models\Payment;

/**
 * Exercises: multiple EnumResource::make from different namespaces (PaymentStatus,
 * Currency from App), whenHas on PaymentMethod enum attribute, whenNotNull.
 *
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => EnumResource::make($this->status),
            'currency' => EnumResource::make($this->currency),
            'amount' => $this->amount,
            'method' => $this->whenHas('method'),
            'reference' => $this->whenNotNull($this->reference),
            'paid_at' => $this->whenNotNull($this->paid_at),
        ];
    }
}
