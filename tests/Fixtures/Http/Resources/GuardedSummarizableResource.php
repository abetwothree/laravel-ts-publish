<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\HeldStats;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\Summarizable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A resource with no model whose mixin names an interface its value object implements, and an `if` that proves it.
 *
 * @mixin Summarizable
 */
final class GuardedSummarizableResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof HeldStats) {
            return ['t' => $this->total, 's' => $this->summary(), 'w' => $this->resource];
        }

        return ['t' => null, 's' => null, 'w' => null];
    }
}
