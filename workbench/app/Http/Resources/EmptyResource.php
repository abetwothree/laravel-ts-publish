<?php

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource with no toArray override — tests guard clause.
 */
class EmptyResource extends JsonResource
{
    // No toArray method — analyzer should return empty ResourceAnalysis
}
