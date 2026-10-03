<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource with one key, an enum resource, under `#[TsCasts]`, while an extends clause names its type.
 *
 * @mixin Post
 */
#[TsExtends('Partial<Record<StatusType, unknown>>')]
#[TsCasts(['k' => 'string | null'])]
class EnumResourceCastBesideExtendsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'k' => EnumResource::make($this->status),
        ];
    }
}
