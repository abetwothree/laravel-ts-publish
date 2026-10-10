<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource with one key under `#[TsCasts]`: a closure that returns one of two enum resources, or null.
 *
 * @mixin Post
 */
#[TsCasts(['state' => 'string | null'])]
class EnumResourceArmsCastClosureResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'state' => $this->when($request->boolean('shown'), function () use ($request) {
                if ($request->boolean('status')) {
                    return EnumResource::make($this->status);
                }

                if ($request->boolean('visibility')) {
                    return EnumResource::make($this->visibility);
                }

                return null;
            }),
        ];
    }
}
