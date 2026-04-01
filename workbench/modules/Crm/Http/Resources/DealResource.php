<?php

declare(strict_types=1);

namespace Workbench\Crm\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\Crm\Models\Deal;

/**
 * Exercises: dual enum conflict — $this->status (App\Enums\Status direct access)
 * vs EnumResource::make($this->crm_status) (Crm\Enums\Status), whenLoaded bare
 * with two different User models (Crm\User + App\User), when conditional,
 * resource wrapping with colliding resource names, dual EnumResource::make.
 *
 * @mixin Deal
 */
class DealResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'value' => $this->value,
            'status' => $this->status,
            'status_enum' => EnumResource::make($this->status),
            'crm_status' => $this->crm_status,
            'crm_enum' => EnumResource::make($this->crm_status),
            'customer' => $this->whenLoaded('customer'),
            'admin' => $this->whenLoaded('admin'),
            'customer_resource' => UserResource::make($this->whenLoaded('customer')),
            'admin_resource' => \Workbench\App\Http\Resources\UserResource::make($this->whenLoaded('admin')),
            'closed_at' => $this->when($this->crm_status?->value === 'churned', $this->updated_at),
        ];
    }
}
