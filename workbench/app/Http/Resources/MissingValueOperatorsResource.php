<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A MissingValue passed on through `??`, a `when()` default and a ternary keeps its key optional.
 *
 * @mixin Post
 */
final class MissingValueOperatorsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $flag = $request->boolean('flag');
        $loaded = $this->whenLoaded('author');

        return [
            'direct_coalesce' => $this->whenLoaded('author') ?? 'none',
            'when_wrap_default' => $this->when($flag, $this->whenLoaded('author'), 'fallback'),
            'when_missing_default' => $this->when($flag, 'value', $this->whenLoaded('author')),
            'ternary' => $flag ? $loaded : 'none',
            'nested_coalesce' => ['inner' => $loaded ?? 'none'],

            // The left is a string that is never null, so the missing right is never reached.
            'coalesce_right_missing' => $this->title ?? $this->whenLoaded('author'),
        ];
    }
}
