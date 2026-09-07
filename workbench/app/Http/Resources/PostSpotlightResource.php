<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Reads a single-model accessor inside an inline member and under a key that differs from the
 * accessor name, so neither the top-level fallback nor the inline import gatherer rescues it.
 *
 * @mixin Post
 */
class PostSpotlightResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'headline' => $this->latest_comment,
            'spotlight' => ['comment' => $this->latest_comment],
        ];
    }
}
