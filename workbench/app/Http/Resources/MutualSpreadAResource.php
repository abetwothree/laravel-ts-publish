<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Regression (Task 32 review, C1): two resources spreading each other must not recurse until
 * memory is exhausted. Mirrors MutualSpreadBResource — see its docblock for the shared rationale.
 */
class MutualSpreadAResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...MutualSpreadBResource::make($this->resource)->resolve(),
            'a_marker' => true,
        ];
    }
}
