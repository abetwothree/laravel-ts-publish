<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * Exercises: a whole return that adds a local's defaults with `+`, whose keys the left side keeps.
 *
 * @mixin Tag
 */
class TagDefaultsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $defaults = ['note' => 'none', 'id' => 'shadowed'];

        return ['id' => $this->id] + $defaults;
    }
}
