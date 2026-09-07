<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Team;

/**
 * A mixed EnumResource/direct-access ternary nested one level down, where both arms read the same
 * list-shaped accessor: they render the same string and the union merge collapses them, so only
 * each arm's own recorded shape still says the [] belongs on both. The direct arm's bare enum type
 * survives the rewrite, so its type import has to come back with it.
 *
 * @mixin Team
 */
class TeamStatusAuditResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'audit' => [
                'status' => $request->boolean('wrap')
                    ? EnumResource::collection($this->status_history)
                    : $this->status_history,
            ],
        ];
    }
}
