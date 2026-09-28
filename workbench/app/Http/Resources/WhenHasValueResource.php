<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Exercises whenHas()/whenAppended()/whenExistsLoaded() typing from the value Laravel actually
 * returns rather than from the named attribute: each one ends in `value($value, ...)`, so a
 * closure's own return is what the property carries. whenHas()/whenExistsLoaded() forward the
 * attribute into the closure's first parameter; whenAppended() forwards nothing.
 *
 * @mixin Post
 */
final class WhenHasValueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'has_title' => $this->whenHas('title', fn ($title): bool => $title !== ''),
            'title_length' => $this->whenHas('title', fn ($title) => strlen($title)),
            'title_passthrough' => $this->whenHas('title', fn ($title) => $title),
            'appended_label' => $this->whenAppended('title_display', fn () => 'label'),
            'comments_flag' => $this->whenExistsLoaded('comments', fn ($exists) => $exists ? 'yes' : 'no'),

            // Returns the parameter bare, so the binding itself is load-bearing: $exists is
            // $this->comments_exists, and only the right flag name publishes its boolean.
            'comments_exists_flag' => $this->whenExistsLoaded('comments', fn ($exists) => $exists),

            // json_decode() returns mixed, so the value resolves to unknown and the named attribute
            // still answers — the value rule may never trade a real type for a fresh `unknown`.
            'title_unresolvable' => $this->whenHas('title', fn ($title) => json_decode($title)),
        ];
    }
}
