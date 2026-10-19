<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Workbench\App\Models\Post;

/**
 * A JSON:API resource declaring toArray(), which toAttributes() falls back to, so its keys are the attributes.
 *
 * @mixin Post
 */
class ToArrayPostResource extends JsonApiResource
{
    /**
     * The keys toAttributes() sends as the attributes.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'title' => $this->title,
            'rating' => $this->rating,
        ];
    }
}
