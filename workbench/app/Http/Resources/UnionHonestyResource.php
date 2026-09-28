<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Exercises: a union arm the engine cannot type is dropped, so the property publishes the arm that is left.
 *
 * One key per recording site, so the dropped-arm audit proves each site fires: the plain ternary and the
 * Elvis go through analyzeClosureUnion(), 'narrowed' through TernaryHandler's instanceof path, and
 * 'data_get_default' through KnownFunctionCallHandler. Line numbers here are pinned by the audit baseline.
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
        ];
    }

    /** Deliberately untyped, so both unions above drop an arm; the audit baseline pins them as residuals. */
    public function opaqueValue()
    {
        return $this->resource->getAttribute('title');
    }
}
