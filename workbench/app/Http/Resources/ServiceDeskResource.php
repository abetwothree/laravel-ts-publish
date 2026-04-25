<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\ServiceDesk;

/**
 * Exercises the inline model FQCN collision scenario.
 *
 * Two relations point to classes with the same basename: Crm\Models\User (direct, via crm_agent)
 * and App\Models\User (embedded inside the inline object from order->only). The transformer must
 * alias both and rewrite the token inside the inline object type string via the inlineModelFqcns
 * tracking path.
 *
 * @mixin ServiceDesk
 */
class ServiceDeskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            // Direct BelongsTo to Crm\Models\User — registers the Crm User in the model FQCN map.
            'crm_agent' => $this->crmAgent,
            // ->only(['user']) on Order: produces an inline object type containing App\Models\User.
            // With the inlineModelFqcns tracking, the type token inside the inline object is aliased.
            'order_requester' => $this->order?->only(['user']),
        ];
    }
}
