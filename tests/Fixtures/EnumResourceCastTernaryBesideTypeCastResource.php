<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource with one key under `#[TsCasts]`, a ternary over two enum resources, beside another key whose
 * cast names both enums' types.
 *
 * @mixin Post
 */
#[TsCasts(['k' => 'string | null', 'b' => 'StatusType | VisibilityType'])]
class EnumResourceCastTernaryBesideTypeCastResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'k' => $request->boolean('status') ? EnumResource::make($this->status) : EnumResource::make($this->visibility),
            'b' => $this->title,
        ];
    }
}
