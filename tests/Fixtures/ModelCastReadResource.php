<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource reading a key its backing model's #[TsCasts] retypes, which no queue of the value may alias.
 *
 * @mixin ModelCastImage
 */
class ModelCastReadResource extends JsonResource
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
