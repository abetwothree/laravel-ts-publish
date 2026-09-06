<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;

/**
 * Three key-less spreads at the top level of toArray(): a resource's resolve(), a model's toArray(),
 * and a collection's toArray(). Each flattens into this resource's own properties.
 *
 * @mixin Comment
 */
class CommentComposedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...PostResource::make($this->post)->resolve(),
            ...$this->user->toArray(),
            ...$this->post->tags->toArray(),
            'id' => $this->id,
        ];
    }
}
