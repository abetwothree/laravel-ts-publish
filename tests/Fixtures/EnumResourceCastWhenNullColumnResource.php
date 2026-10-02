<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource with one key under `#[TsCasts]`: a `whenNull()` over a column, defaulting to an enum resource.
 *
 * @mixin Post
 */
#[TsCasts(['k' => 'string | null'])]
class EnumResourceCastWhenNullColumnResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'k' => $this->whenNull($this->title, EnumResource::make($this->visibility)),
        ];
    }
}
