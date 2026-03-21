<?php

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsResourceCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;

/**
 * @mixin Comment
 */
#[TsResourceCasts([
    'metadata' => 'Record<string, unknown>',
    'flagged_at' => ['type' => 'string | null', 'optional' => true],
])]
class CommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'is_flagged' => $this->is_flagged,
            'flagged_at' => $this->flagged_at,
            'metadata' => $this->metadata,
            'author' => UserResource::make($this->whenLoaded('user')),
            'post' => PostResource::make($this->whenLoaded('post')),
        ];
    }
}
