<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\SubscribedTeam;
use Workbench\App\Models\Team;

/**
 * A `$this->resource instanceof <Model>` ternary narrows the backing model for its true arm, so a
 * relation only the subclass declares resolves there.
 *
 * @mixin Team
 */
final class TeamSubscriberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'subscriber_name' => $this->resource instanceof SubscribedTeam ? $this->resource->subscriber?->name : null,
        ];
    }
}
