<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Three enum members, two of them the same enum: the import queue must carry three entries, not two.
 *
 * @mixin Post
 */
class PostEnumTrioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trio' => ['a' => $this->status, 'b' => $this->status, 'c' => $this->priority],
        ];
    }
}
