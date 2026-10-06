<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A returned variable is one branch beside a literal and a `return []` guard, so every key publishes optional.
 *
 * @mixin Tag
 */
final class ReturnedVariableBranchesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($request->boolean('hidden')) {
            return [];
        }

        if ($request->boolean('compact')) {
            return ['id' => $this->id];
        }

        $data = ['id' => $this->id, 'name' => $this->name];

        return $data;
    }
}
