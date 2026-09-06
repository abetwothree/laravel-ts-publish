<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Regression (Task 32 review, C1): a resource spreading itself must not recurse until memory is
 * exhausted. AstEngine::analyzeMethod()'s cycle guard returns an empty analysis for the re-entrant
 * call, so only 'marker' should ever appear.
 */
class SelfSpreadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...SelfSpreadResource::make($this->resource)->resolve(),
            'marker' => true,
        ];
    }
}
