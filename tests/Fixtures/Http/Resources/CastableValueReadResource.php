<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\BaseDeclaringCastablePost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Reads columns cast to Castables that are not the value off the model, which sends the value the built caster's
 * get() returns.
 *
 * @mixin BaseDeclaringCastablePost
 */
final class CastableValueReadResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'declared_base' => $this->title,
            'declared_base_whole' => $this->resource->title,
            'propertied' => $this->content,
        ];
    }
}
