<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;

/**
 * A test-only resource whose class-level #[TsCasts] has no import, over a toArray() one that has, for one key.
 *
 * @mixin Image
 */
#[TsCasts(['reviewable' => 'UserResource | null'])]
class ClassCastOverMethodCastResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[TsCasts(['reviewable' => ['type' => 'UserResource | null', 'import' => '@js/types/user']])]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reviewable' => $this->whenLoaded('reviewable', fn ($subject) => $subject->toResource()),
        ];
    }
}
