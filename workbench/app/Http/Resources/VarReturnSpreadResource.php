<?php

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\Concerns\IncludesConditionalData;
use Workbench\App\Models\User;

/**
 * Fixture resource exercising variable-return trait method spreads.
 *
 * @mixin User
 */
class VarReturnSpreadResource extends JsonResource
{
    use IncludesConditionalData;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            ...$this->includeConditionalData(),
            ...$this->includeDimAssigned(),
            ...$this->includeMultiBranch(),
            ...$this->includeFromMethodCall(),
            ...$this->includeConditionalBase(),
            ...$this->returnsFromForEach(),
            ...$this->returnsFromSimpleForEach(),
            ...$this->returnsFromForLoop(),
            ...$this->returnsFromWhileLoop(),
            ...$this->returnsFromDoWhile(),
            ...$this->includesDuplicateKey(),
        ];
    }
}
