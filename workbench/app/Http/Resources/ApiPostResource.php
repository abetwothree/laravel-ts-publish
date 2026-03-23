<?php

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Workbench\App\Models\Post;

/**
 * @mixin Post
 */
class ApiPostResource extends PostResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'status' => $this->status,
            'visibility' => $this->visibility,
            'priority' => $this->priority,
        ];
    }
}
