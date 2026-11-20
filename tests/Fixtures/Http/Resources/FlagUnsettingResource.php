<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\OffsetClaimingPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Unsets its post's public `flagged`, so isset() of it then runs the post's `__isset()`, which the post's own
 * `offsetExists()` answers apart from reading it.
 *
 * @mixin OffsetClaimingPost
 */
final class FlagUnsettingResource extends JsonResource
{
    /**
     * The post's id, its flag unset first.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        unset($this->resource->flagged);

        return ['id' => $this->id];
    }
}
