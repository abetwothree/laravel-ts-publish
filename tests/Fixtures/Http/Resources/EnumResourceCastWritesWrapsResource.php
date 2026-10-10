<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource with one key under `#[TsCasts]` that writes both wraps: a ternary over two enum resources.
 *
 * @mixin Post
 */
#[TsCasts(['k' => 'AsEnum<typeof Status> | AsEnum<typeof Visibility> | null'])]
class EnumResourceCastWritesWrapsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'k' => $request->boolean('status') ? EnumResource::make($this->status) : EnumResource::make($this->visibility),
        ];
    }
}
