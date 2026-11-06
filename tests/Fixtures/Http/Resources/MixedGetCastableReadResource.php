<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\MixedGetCastablePost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Reads columns whose caster's get() declares only `mixed` off the model, which sends the value get() returns.
 *
 * @mixin MixedGetCastablePost
 */
final class MixedGetCastableReadResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'castable_mixed_get' => $this->title,
            'caster_mixed_get' => $this->content,
            'nested_only' => $this->resource->only(['title', 'content']),
            'opaque_castable' => $this->category,
            'opaque_only' => $this->resource->only(['category']),
        ];
    }
}
