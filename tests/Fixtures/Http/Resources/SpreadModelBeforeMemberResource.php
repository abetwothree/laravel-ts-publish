<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource whose member spreads one User model's toArray(), then names the other User a relation reads.
 *
 * @mixin Warehouse
 */
class SpreadModelBeforeMemberResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'manager' => $this->whenLoaded('manager', fn ($manager) => [
                ...$manager->toArray(),
                'peer' => $this->primaryContact,
            ]),
        ];
    }
}
