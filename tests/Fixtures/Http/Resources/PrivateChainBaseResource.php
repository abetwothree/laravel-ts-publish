<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose `toArray()` chains its own private `chain()`, which hands the built instance back, onto
 * an instance of the runtime class it builds, which PHP runs over PrivateChainResource's public namesake, and onto an
 * OtherChainResource, whose own `chain()` PHP runs.
 */
class PrivateChainBaseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'nested' => $this->when($this->resource === null, fn () => static::make(new Post)->chain()),
            'other' => OtherChainResource::make(new Post)->chain(),
        ];
    }

    /** Hand this instance back. */
    private function chain(): static
    {
        return $this;
    }
}
