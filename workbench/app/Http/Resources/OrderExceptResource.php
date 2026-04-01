<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Order;

/**
 * Exercises return $this->except([...]) as a direct return.
 *
 * @mixin Order
 */
class OrderExceptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->except([
            'ip_address',
            'user_agent',
        ]);
    }
}
