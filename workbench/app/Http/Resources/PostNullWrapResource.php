<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Resources built around a payload that is only null, which JsonResource::filter() sends as null.
 *
 * @mixin Post
 */
class PostNullWrapResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $nothing = null;

        return [
            'null_new' => new UserResource(null),
            'null_make' => UserResource::make(null),
            'null_default' => $this->when($this->title !== '', fn ($author = new UserResource(null)) => $author),
            'null_local' => new UserResource($nothing),
            'author_new' => new UserResource($this->author),
            'nested' => ['author' => new UserResource(null)],
        ];
    }
}
