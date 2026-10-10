<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose #[TsCasts] spells one of the two enums its ternary wraps.
 *
 * @mixin Post
 */
#[TsCasts(['k' => 'StatusType | null'])]
class CastOneOfTwoWrapsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'k' => $request->boolean('a') ? EnumResource::make($this->status) : EnumResource::make($this->visibility),
        ];
    }
}
