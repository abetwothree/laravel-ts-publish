<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource with one key under `#[TsCasts]`: a `whenNull()` whose value and default are enum resources.
 *
 * @mixin Post
 */
#[TsCasts(['k' => 'string | null'])]
class EnumResourceCastWhenNullPairResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'k' => $this->whenNull(EnumResource::make($this->status), EnumResource::make($this->visibility)),
        ];
    }
}
