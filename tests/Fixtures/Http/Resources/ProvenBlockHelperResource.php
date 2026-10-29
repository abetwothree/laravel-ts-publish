<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\SubscribedTeam;
use Workbench\App\Models\Team;

/**
 * A helper read as a value inside a block an `instanceof` test on `$this->resource` proves.
 *
 * @mixin Team
 */
final class ProvenBlockHelperResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof SubscribedTeam) {
            return ['subscriber' => $this->subscriberName()];
        }

        return ['subscriber' => null];
    }

    /** The subscriber's name, which only the subclass the test proves has. */
    private function subscriberName()
    {
        return $this->resource->subscriber?->name;
    }
}
