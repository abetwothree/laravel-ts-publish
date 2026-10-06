<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Category;

/**
 * Every whenLoaded() spelling over `parent`, a BelongsTo whose nullable foreign key makes it load as null, and over
 * `children`, a HasMany that loads as a collection. Laravel returns null for a relation loaded as null before it reads
 * the value, so each `parent` key publishes `| null`; a `children` key never does.
 *
 * @mixin Category
 */
class CategoryLineageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_name' => $this->whenLoaded('parent', fn ($parent) => $parent->name),
            'parent_list' => $this->whenLoaded('parent', fn (...$parents) => $parents),
            'parent_label' => $this->whenLoaded('parent', 'loaded'),
            'parent_or_absent' => $this->whenLoaded('parent', null, 'absent'),
            'parent_named_default' => $this->whenLoaded('parent', default: 'absent'),
            'parent_name_or_absent' => $this->whenLoaded('parent', fn ($parent) => $parent->name, 'absent'),
            'parent_callable' => $this->whenLoaded('parent', CategoryResource::make(...)),
            'children_names' => $this->whenLoaded('children', fn ($children) => $children->pluck('name')),
            'children_list' => $this->whenLoaded('children', fn (...$children) => $children),
        ];
    }
}
