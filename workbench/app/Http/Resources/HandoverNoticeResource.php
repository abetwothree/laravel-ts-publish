<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Handover;

/**
 * Reads two models that share a name through the conditional helpers: each arm keeps its own class.
 *
 * @mixin Handover
 */
class HandoverNoticeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'counterparty' => $this->when($request->boolean('crm'), $this->receiver, $this->sender),
            'unclaimed' => $this->whenNull($this->party, $this->receiver),
        ];
    }
}
