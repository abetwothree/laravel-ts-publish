<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Workbench\App\Models\Post;

/**
 * A JSON:API resource whose toAttributes() returns a variable: each keyless name in it reads the model attribute.
 *
 * @mixin Post
 */
class VariableAttributesPostResource extends JsonApiResource
{
    /**
     * The attribute names, with the content added when the request asks for it.
     *
     * @return array<int|string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        $attributes = ['title', 'word_count'];

        if ($request->boolean('full')) {
            $attributes[] = 'content';
        }

        $attributes['is_long'] = $this->word_count > 1000;

        return $attributes;
    }
}
