<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource with one key, an enum resource, under a `#[TsCasts]` that imports a type named like the enum's
 * const.
 *
 * @mixin Post
 */
#[TsCasts(['k' => ['type' => 'Visibility | null', 'import' => '@/types/visibility']])]
class EnumResourceCastImportConstNameResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'k' => EnumResource::make($this->visibility),
        ];
    }
}
