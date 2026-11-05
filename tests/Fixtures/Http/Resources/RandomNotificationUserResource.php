<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\User;

/**
 * Picks its user's framework-model notifications at random, directly and through a resource built around the user.
 *
 * @extends JsonResource<User>
 */
class RandomNotificationUserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'direct' => $this->resource->notifications->random(),
            'built' => NotifiedUserResource::make($this->resource)->notifications->random(),
            'built_two' => NotifiedUserResource::make($this->resource)->notifications->random(2),
            'built_list' => NotifiedUserResource::make($this->resource)->notifications,
        ];
    }
}
