<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource with one key under `#[TsCasts]`: a `when()` whose value and default are two enum resources.
 *
 * @mixin Post
 */
#[TsCasts(['state' => 'string | null'])]
class EnumResourceArmsCastWhenResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'state' => $this->when(
                $request->boolean('status'),
                EnumResource::make($this->status),
                EnumResource::make($this->visibility),
            ),
        ];
    }
}
