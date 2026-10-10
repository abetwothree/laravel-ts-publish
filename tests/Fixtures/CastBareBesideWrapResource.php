<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose #[TsCasts] retypes a bare enum read beside a key that wraps the same enum.
 *
 * @mixin Post
 */
#[TsCasts(['k' => 'string'])]
class CastBareBesideWrapResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'k' => $this->status,
            'a' => EnumResource::make($this->status),
        ];
    }
}
