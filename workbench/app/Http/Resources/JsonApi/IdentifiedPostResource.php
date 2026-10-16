<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Workbench\App\Models\Post;

/**
 * A JSON:API resource overriding toId() with an int, which Laravel sends as a string, and toType() with a literal.
 *
 * @mixin Post
 */
class IdentifiedPostResource extends JsonApiResource
{
    public $attributes = ['title'];

    /**
     * The resource object's id.
     */
    public function toId(Request $request): int
    {
        return $this->id * 1000;
    }

    /**
     * The resource object's type.
     */
    public function toType(Request $request): string
    {
        return 'articles';
    }
}
