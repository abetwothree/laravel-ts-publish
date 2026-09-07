<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Regression (Task 32 review, C1): the other half of the mutual pair. A spreads B, B spreads A —
 * AstEngine::analyzeMethod()'s cycle guard must break the loop wherever it's first re-entered.
 */
class MutualSpreadBResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...MutualSpreadAResource::make($this->resource)->resolve(),
            'b_marker' => true,
        ];
    }
}
