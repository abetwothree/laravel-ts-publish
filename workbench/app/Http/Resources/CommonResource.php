<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\Concerns\IncludesExtras;
use Workbench\App\Http\Resources\Concerns\IncludesMorphValue;
use Workbench\App\Models\Comment;

/**
 * @mixin Comment
 */
abstract class CommonResource extends JsonResource
{
    use IncludesExtras;
    use IncludesMorphValue;

    public function toArray(Request $request): array
    {
        return [
            ...$this->includeMorphValue(),
            ...$this->includeTypedExtras(),
            ...$this->includeNoDocs(),
            ...$this->includeNoShape(),
            ...$this->includeMultilineShape(),
            ...$this->includeCastedExtras(),
        ];
    }
}
