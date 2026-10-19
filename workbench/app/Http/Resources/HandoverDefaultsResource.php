<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Handover;

/**
 * Returns an array union whole: the filtered attributes keep their own types, and the defaults add only the keys the
 * filter leaves out.
 *
 * @mixin Handover
 */
class HandoverDefaultsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return $this->only(['id', 'sender_id']) + ['sender_id' => 0, 'note' => 'none'];
    }
}
