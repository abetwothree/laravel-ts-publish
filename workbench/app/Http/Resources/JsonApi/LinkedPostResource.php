<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Workbench\App\Models\Post;

/**
 * A JSON:API resource overriding toLinks() and toMeta().
 *
 * @mixin Post
 */
class LinkedPostResource extends JsonApiResource
{
    public $attributes = ['title'];

    /**
     * The resource object's links.
     *
     * @return array<string, string>
     */
    public function toLinks(Request $request): array
    {
        return ['self' => 'https://example.test/posts/'.$this->id];
    }

    /**
     * The resource object's meta.
     *
     * @return array<string, mixed>
     */
    public function toMeta(Request $request): array
    {
        return ['readable' => true, 'words' => $this->word_count];
    }
}
