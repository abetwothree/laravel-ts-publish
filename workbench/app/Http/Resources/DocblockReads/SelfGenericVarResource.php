<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\DocblockReads;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\DocblockReads\CategoryBranch;

/**
 * `self` inside a generic `@var` in a final class's trait, beside the class named there (GH-134; plan task T3).
 *
 * @property-read CategoryBranch $resource
 */
class SelfGenericVarResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'self_kid_names' => $this->resource->selfKidNames(),
            'named_kid_names' => $this->resource->namedKidNames(),
            'self_first_kid_name' => $this->resource->selfFirstKidName(),
            'named_first_kid_name' => $this->resource->namedFirstKidName(),
        ];
    }
}
