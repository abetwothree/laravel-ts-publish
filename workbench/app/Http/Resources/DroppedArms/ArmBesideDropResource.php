<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\DroppedArms;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\DroppedArms\ArmHolder;

/**
 * Under Laravel's `array<string, mixed>` stub, an arm no rule types beside a typed one (GH-161): `cache()` sends
 * `null` here, beside an arrow override, a list of them, and lists of two same-named users.
 *
 * @property-read ArmHolder $resource
 */
class ArmBesideDropResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'cb' => $this->resource->flag ? $this->resource->arrow() : cache('cb'),
            'cbs' => [$this->resource->flag ? $this->resource->arrow() : cache('cb')],
            'users' => $this->resource->flag
                ? [$this->resource->owner]
                : [$this->resource->pick === 'a' ? $this->resource->contact : cache('u')],
        ];
    }
}
