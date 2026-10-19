<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Sets `status` before merging its own model, whose `status` is an enum, so the earlier value is the one published.
 *
 * @mixin Post
 */
final class MergedHeldEnumKeyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['status' => $this->content, $this->merge(fn () => $this->resource)];
    }
}
