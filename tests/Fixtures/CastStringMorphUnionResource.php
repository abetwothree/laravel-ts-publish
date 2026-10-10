<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;

/**
 * A test-only resource whose #[TsCasts] retypes a key of two same-named resources as an object shape.
 *
 * @mixin Image
 */
#[TsCasts(['reviewable' => '{ id: number } | null'])]
class CastStringMorphUnionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'reviewable' => $this->whenLoaded('reviewable', fn ($subject) => $subject->toResource()),
        ];
    }
}
