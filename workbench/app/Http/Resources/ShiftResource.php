<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Workbench\App\Models\Shift;
use Workbench\App\Services\ShiftClock;

/**
 * Publishes each value as json_encode() writes it, not as `__toString()` reads it.
 *
 * @mixin Shift
 */
class ShiftResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Exception $fault */
        $fault = $this->clock()->lastFault();

        return [
            'fault' => $fault,
            'handover_note' => $this->clock()->handoverNote(),
            'checked_at' => $request->boolean('fresh') ? new Carbon : null,
        ];
    }

    private function clock(): ShiftClock
    {
        return new ShiftClock;
    }
}
