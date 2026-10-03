<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource with one key, an enum resource, under a `#[TsCasts]` that writes the enum's wrap itself.
 *
 * @mixin Post
 */
#[TsCasts(['k' => 'AsEnum<typeof Status> | string'])]
class EnumResourceCastWritesWrapResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'k' => EnumResource::make($this->status),
        ];
    }
}
