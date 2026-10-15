<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\MacroShadowedPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A resource whose bare calls name resource macros, most sharing a name with a model method.
 *
 * @mixin MacroShadowedPost
 */
class MacroShadowingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'label' => $this->label(),
            'label_nullsafe' => $this?->label(),
            'label_proxy' => $this->resource->label(),
            'label_static' => static::label(),
            'label_this_static' => $this::label(),
            'label_self' => self::label(),
            'moment_length' => $this->moment()->length(),
            'shout' => $this->shout(),
            'typed_shout' => $this->typedShout(),
            'badge' => $this->badge(),
            'word_count' => $this->wordCount(),
            'title_length' => $this->titleLength(),
            'ticket' => $this->ticket(),
            'codes' => $this->codes(),
            'arrow_doc' => $this->arrowDoc(),
            'shout_static' => static::shout(),
        ];
    }
}
