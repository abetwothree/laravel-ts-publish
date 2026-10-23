<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Category;

/**
 * Exercises: a returned variable whose keys exclusive branches write. Each branch of an if chain holds its own value
 * for a key, so a key every branch of a chain ending in else writes is required with each branch's type, and a key a
 * chain without else writes keeps the value it held before as one more arm.
 *
 * @mixin Category
 */
class CategoryBranchesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id, 'label' => $this->sort_order];

        if ($this->is_active) {
            $data['mode'] = 1;
        } else {
            $data['mode'] = 'off';
        }

        if ($this->parent === null) {
            $data['top'] = null;
        } else {
            $data['top'] = CategoryResource::make($this->parent);
        }

        if ($this->sort_order === 1) {
            $data['tier'] = 'first';
            $data['first_only'] = true;
        } elseif ($this->sort_order === 2) {
            $data['tier'] = 2;
        } else {
            $data['tier'] = false;
        }

        if ($this->description !== null) {
            $data['label'] = $this->name;
            $data['described'] = true;
        }

        return $data;
    }
}
