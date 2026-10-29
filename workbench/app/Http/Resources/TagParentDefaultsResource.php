<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * Exercises: parent::toArray() `+` a local's defaults, as the whole return.
 *
 * @mixin Tag
 */
class TagParentDefaultsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $defaults = ['note' => 'none'];

        return parent::toArray($request) + $defaults;
    }
}
