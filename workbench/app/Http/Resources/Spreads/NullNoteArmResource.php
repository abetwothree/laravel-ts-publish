<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Spreads;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An arm ArmBesideMapResource spreads beside two maps, whose `note` is always null (GH-161 D3).
 */
class NullNoteArmResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => 8, 'note' => null];
    }
}
