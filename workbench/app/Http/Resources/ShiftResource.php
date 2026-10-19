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
    /**
     * The shift's clock readings.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Exception $fault */
        $fault = $this->clock()->lastFault();

        return [
            'last_fault' => $this->clock()->lastFault(),
            'fault' => $fault,
            'started_at' => $this->clock()->startedAt(),
            'handover_note' => $this->clock()->handoverNote(),
            'next_bell' => $this->clock()->nextBell(),
            'checked_at' => $request->boolean('fresh') ? new Carbon : null,
        ];
    }

    /**
     * The clock the readings come from.
     */
    private function clock(): ShiftClock
    {
        return new ShiftClock;
    }
}
