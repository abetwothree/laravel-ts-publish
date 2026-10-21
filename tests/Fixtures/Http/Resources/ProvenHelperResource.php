<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\SubscribedTeam;
use Workbench\App\Models\Team;

/**
 * A helper read as a value under the caller's `instanceof` proof on `$this->resource`, and without it.
 *
 * @mixin Team
 */
final class ProvenHelperResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'proven' => $this->resource instanceof SubscribedTeam ? $this->subscriberName() : null,
            'unproven' => $this->subscriberName(),
            'name' => $this->name,
        ];
    }

    /** The subscriber's name, which only the subclass the caller proves has. */
    private function subscriberName()
    {
        return $this->resource->subscriber?->name;
    }
}
