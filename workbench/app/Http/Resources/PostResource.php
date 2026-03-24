<?php

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\Concerns\IncludesMorphValue;
use Workbench\App\Models\Post;

/**
 * @mixin Post
 */
class PostResource extends JsonResource
{
    use IncludesMorphValue;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->includeMorphValue(),
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'status' => EnumResource::make($this->status),
            'status_new' => new EnumResource($this->status),
            'visibility' => EnumResource::make($this->visibility),
            'visibility_new' => new EnumResource($this->visibility),
            'priority' => EnumResource::make($this->priority),
            'priority_new' => new EnumResource($this->priority),
        ];
    }
}
