<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose one key, an enum resource, sits in a helper method that `toArray()` spreads, under the
 * `#[TsCasts]` of that method.
 *
 * @mixin Post
 */
class EnumResourceCastHelperResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...$this->state(),
        ];
    }

    /** @return array<string, mixed> */
    #[TsCasts(['k' => 'string | null'])]
    protected function state(): array
    {
        return ['k' => EnumResource::make($this->visibility)];
    }
}
