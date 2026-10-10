<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;

/**
 * A test-only resource whose #[TsCasts], with no import, spells the name both resources of its key share, once.
 *
 * @mixin Image
 */
#[TsCasts(['reviewable' => 'UserResource | null'])]
class CastSpelledMorphUnionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'reviewable' => $this->whenLoaded('reviewable', fn ($subject) => $subject->toResource()),
        ];
    }
}
