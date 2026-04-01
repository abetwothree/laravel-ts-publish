<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\Concerns\IncludesExtras;
use Workbench\App\Models\User;

/** @mixin User */
class TraitSpreadCoverageResource extends JsonResource
{
    use IncludesExtras;

    public function toArray(Request $request): array
    {
        return [
            ...$this->includeTypedExtras(),
            ...$this->includeNoDocs(),
            ...$this->includeNoShape(),
            ...$this->includeMultilineShape(),
            ...$this->includeCastedExtras(),
        ];
    }
}
