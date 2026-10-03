<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource with one key under `#[TsCasts]` that writes the wrap of another enum: an inline array that holds
 * an enum resource.
 *
 * @mixin Post
 */
#[TsCasts(['k' => '{ inner: AsEnum<typeof Status> }'])]
class EnumResourceCastWritesOtherWrapInlineResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'k' => ['inner' => EnumResource::make($this->visibility)],
        ];
    }
}
