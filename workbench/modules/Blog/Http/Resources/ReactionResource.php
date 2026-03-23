<?php

namespace Workbench\Blog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\Blog\Models\Reaction;

/**
 * Exercises: multiple whenLoaded bare — both same-module (Article)
 * and cross-module (App\User) model type resolution.
 *
 * @mixin Reaction
 */
class ReactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'emoji' => $this->emoji,
            'article' => $this->whenLoaded('article'),
            'user' => $this->whenLoaded('user'),
        ];
    }
}
