<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Handover;

/**
 * Unions two models that share a name by `??`, a ternary and an inline array, and reads an accessor that does the same.
 *
 * @mixin Handover
 */
class HandoverResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'party' => $this->sender ?? $this->receiver,
            'picked' => $request->boolean('crm') ? $this->receiver : $this->sender,
            'pair' => ['first' => $this->sender, 'either' => $this->sender ?? $this->receiver],
            'parties' => $this->parties,
            'audience' => $this->audience,
        ];
    }
}
