<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource writing a key, then spreading the keys another resource's resolve() sends, one of them maybe.
 *
 * @mixin Post
 */
final class SpreadFoldResolvedArmResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['title' => $this->id, ...SpreadFoldTitledResource::make($this->resource)->resolve($request)];
    }
}
