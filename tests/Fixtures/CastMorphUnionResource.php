<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;

/**
 * A test-only resource whose class-level #[TsCasts] retypes a key that reads Image::reviewable()'s resource union.
 *
 * @mixin Image
 */
#[TsCasts(['reviewable' => ['type' => 'UserResource | null', 'import' => '@js/types/user']])]
class CastMorphUnionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reviewable' => $this->whenLoaded('reviewable', fn ($subject) => $subject->toResource()),
        ];
    }
}
