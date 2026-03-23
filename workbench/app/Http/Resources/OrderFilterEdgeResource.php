<?php

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Edge-case resource for $this->only() / $this->except() guard clause coverage.
 * No @mixin — so buildModelDelegatedAnalysis() returns null.
 */
class OrderFilterEdgeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // only() with a variable arg (not an array literal) — tests non-Array_ guard
            ...$this->only($request->input('fields', [])),
            // except() with empty array — tests empty keys guard
            ...$this->except([]),
            // only() with valid keys but no model — tests null analyzeOnlyFilter
            ...$this->only(['id', 'name']),
            // except() with valid keys but no model — tests null analyzeExceptFilter
            ...$this->except(['secret']),
        ];
    }
}
