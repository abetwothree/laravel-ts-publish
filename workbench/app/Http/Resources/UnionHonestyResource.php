<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Exercises: a union arm the engine cannot type is dropped, so the property publishes the arm that is left.
 *
 * Keys per recording site, each pinned by the audit: the ternary and the Elvis through analyzeClosureUnion(), then
 * 'narrowed', 'data_get_default', 'conditional_default', 'match_arm' and 'coalesce_right' through their handlers, the
 * 'binding_join' keys through a parameter's join past a block, and 'walk_branch_join' through a local's branch join.
 *
 * @mixin Post
 */
final class UnionHonestyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'elvis' => $this->opaqueValue() ?: null,
            'ternary' => $this->id > 0 ? $this->opaqueValue() : null,
            'still_typed' => $this->id > 0 ? $this->title : null,
            'narrowed' => $this->resource instanceof Post ? $this->opaqueValue() : null,
            'data_get_default' => data_get($this->resource, 'title', $this->opaqueValue()),
            'conditional_default' => $this->when($this->id > 0, $this->title, $this->opaqueValue()),
            'match_arm' => match (true) {
                $this->id > 1 => $this->title, default => $this->opaqueValue()
            },
            'coalesce_right' => $this->title ?? $this->opaqueValue(),
            // The value the callback is passed, which no rule types, is left out beside the one it writes.
            'binding_join_old' => $this->transform($this->opaqueValue(), function ($value) {
                if ($this->id > 1) {
                    $value = $this->title;
                }

                return $value;
            }),
            // The value written inside the block is left out, since the title passed still reaches the read.
            'binding_join_written' => $this->transform($this->title, function ($title) {
                if ($this->id > 1) {
                    $title = $this->opaqueValue();
                }

                return $title;
            }),
            'walk_branch_join' => $this->when($this->id > 0, function () {
                $built = ['id' => $this->id];

                if ($this->id > 1) {
                    $built['value'] = $this->opaqueValue();
                } else {
                    $built['value'] = $this->title;
                }

                return $built;
            }),
        ];
    }

    /** Deliberately untyped, so each union above drops an arm; the audit baseline pins them as residuals. */
    public function opaqueValue()
    {
        return $this->resource->getAttribute('title');
    }
}
