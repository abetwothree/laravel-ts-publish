<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ModelCastImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource whose class-level #[TsCasts] has no import, over its model's cast with an import, for one key.
 *
 * @mixin ModelCastImage
 */
#[TsCasts(['reviewable' => 'User | null'])]
class CastOverModelCastResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reviewable' => $this->reviewable,
        ];
    }
}
