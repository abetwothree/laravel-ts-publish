<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\WrappedPriorityPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Reads a post's accessor-built EnumResource wrap: where filter() reaches it, in a map kept as a Collection, and in one
 * `->all()` hands to filter().
 *
 * @mixin WrappedPriorityPost
 */
class WrappedPriorityPostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'accessor' => $this->priority_wrap,
            'accessor_kept' => collect([$this->resource])->map(fn (WrappedPriorityPost $p) => $p->priority_wrap),
            'accessor_all' => collect([$this->resource])->map(fn (WrappedPriorityPost $p) => $p->priority_wrap)->all(),
        ];
    }
}
