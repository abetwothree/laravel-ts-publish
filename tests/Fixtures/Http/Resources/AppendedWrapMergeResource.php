<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\AppendedPriorityWrapPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Merges its post's own array beside a key of its own.
 *
 * @mixin AppendedPriorityWrapPost
 */
class AppendedWrapMergeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [$this->merge($this->resource), 'x' => 1];
    }
}
