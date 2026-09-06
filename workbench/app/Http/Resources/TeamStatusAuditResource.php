<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Team;

/**
 * The direct arm's enum type is substituted away by the wrapped arm, so the bare enum type must not
 * be imported.
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
