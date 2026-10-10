<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource with one key, an enum resource, under a `#[TsCasts]` that brings its own import.
 *
 * @mixin Post
 */
#[TsCasts(['k' => ['type' => 'Label | null', 'import' => '@/types/label']])]
class EnumResourceCastImportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'k' => EnumResource::make($this->visibility),
        ];
    }
}
