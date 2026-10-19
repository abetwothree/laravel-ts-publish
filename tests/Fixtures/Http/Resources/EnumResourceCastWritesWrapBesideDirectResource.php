<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource with a key, an enum resource, under a `#[TsCasts]` that writes the enum's wrap itself, beside a
 * key that reads the same enum directly.
 *
 * @mixin Post
 */
#[TsCasts(['k' => 'AsEnum<typeof Status> | string'])]
class EnumResourceCastWritesWrapBesideDirectResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'k' => EnumResource::make($this->status),
            'a' => $this->status,
        ];
    }
}
