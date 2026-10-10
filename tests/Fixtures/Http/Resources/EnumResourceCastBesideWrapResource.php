<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource with a key under `#[TsCasts]`, an enum resource, beside a key that wraps the same enum.
 *
 * @mixin Post
 */
#[TsCasts(['k' => 'string | null'])]
class EnumResourceCastBesideWrapResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'k' => EnumResource::make($this->status),
            'a' => EnumResource::make($this->status),
        ];
    }
}
