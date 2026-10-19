<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource with one key, an enum resource, under a `#[TsCasts]` on `toArray()`.
 *
 * @mixin Post
 */
class EnumResourceCastMethodResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[TsCasts(['k' => 'string | null'])]
    public function toArray(Request $request): array
    {
        return [
            'k' => EnumResource::make($this->visibility),
        ];
    }
}
