<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\DroppedArms;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\DroppedArms\ArmHolder;

/**
 * The same-named users of ArmBesideDropResource joined by a returned variable's branches (GH-161).
 *
 * @property-read ArmHolder $resource
 */
class WalkedArmDropResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [];

        if ($this->resource->flag) {
            $data['users'] = [$this->resource->owner];
        } else {
            $data['users'] = [$this->resource->pick === 'a' ? $this->resource->contact : cache('u')];
        }

        return $data;
    }
}
