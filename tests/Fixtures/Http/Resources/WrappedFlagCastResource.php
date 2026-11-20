<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\WrappedFlagPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Reads of attributes a custom cast types `boolean` while PHP holds an object: through the cast's own override, and
 * through the override of the value class its get() returns.
 *
 * @mixin WrappedFlagPost
 */
class WrappedFlagCastResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'is_pinned' => $this->is_pinned,
            'pinned_title' => $this->is_pinned ? $this->title : null,
            'status' => $this->status,
            'status_title' => $this->status ? $this->title : null,
        ];
    }
}
