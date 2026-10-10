<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource with one key, an enum resource, under `#[TsCasts]`, beside another key whose cast names the
 * enum's type.
 *
 * @mixin Post
 */
#[TsCasts(['k' => 'string | null', 'b' => 'StatusType[]'])]
class EnumResourceCastBesideTypeCastResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'k' => EnumResource::make($this->status),
            'b' => $this->title,
        ];
    }
}
