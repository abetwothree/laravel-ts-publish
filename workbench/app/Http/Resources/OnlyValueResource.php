<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * only() away from a relation receiver: a top-level spread that names a withCount() virtual the schema
 * lacks, and two value-position calls — on $this (forwarded to the model) and on a whenLoaded closure
 * parameter — which must reference the model the receiver holds rather than only()'s vague array return.
 *
 * The last two keys are the counter-case: with no literal key list there is nothing to Pick<>, so the receiver rule
 * answers both with Record<string, unknown>, the attribute-keyed array only() returns, instead of unknown.
 *
 * @mixin Post
 */
final class OnlyValueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->only(['id', 'comments_count']),
            'summary' => $this->when(true, fn () => $this->only(['id', 'title'])),
            'category' => $this->whenLoaded('categoryRel', fn ($category) => $category->only(['id', 'name'])),
            'dynamic' => $this->only($request->input('fields')),
            'dynamic_category' => $this->whenLoaded('categoryRel', fn ($category) => $category->only($request->input('fields'))),
        ];
    }
}
