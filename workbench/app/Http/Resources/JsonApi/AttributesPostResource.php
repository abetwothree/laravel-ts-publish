<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Workbench\App\Models\Post;

/**
 * A JSON:API resource whose attributes come from a toAttributes() override, one of them conditional.
 *
 * @mixin Post
 */
class AttributesPostResource extends JsonApiResource
{
    /**
     * The attributes the resource object sends.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'title' => $this->title,
            'word_count' => $this->word_count,
            'is_long' => $this->word_count > 1000,
            'secret' => $this->when($request->user() !== null, 'hidden'),
        ];
    }
}
