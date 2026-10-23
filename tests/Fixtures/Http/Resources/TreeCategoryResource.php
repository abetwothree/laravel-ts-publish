<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\TreeCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\CategoryResource;

/**
 * whenLoaded() values that hand the loaded relation to a resource's collection(): a to-many relation only a docblock
 * declares, one the engine cannot read, a declared to-many one and a nullable to-one one, beside a to-one wrap.
 *
 * @mixin TreeCategory
 */
class TreeCategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'ancestors' => $this->whenLoaded('ancestors', CategoryResource::collection(...)),
            'ancestors_closure' => $this->whenLoaded('ancestors', fn ($ancestors) => CategoryResource::collection($ancestors)),
            'ancestors_self' => $this->whenLoaded('ancestors', static::collection(...)),
            'ancestors_bare' => $this->whenLoaded('ancestors'),
            'branches' => $this->whenLoaded('branches', CategoryResource::collection(...)),
            'branches_closure' => $this->whenLoaded('branches', fn ($branches) => CategoryResource::collection($branches)),
            'branches_bare' => $this->whenLoaded('branches'),
            'children' => $this->whenLoaded('children', CategoryResource::collection(...)),
            'parent_wrap' => $this->whenLoaded('parent', fn ($parent) => CategoryResource::make($parent)),
            'parent_collected' => $this->whenLoaded('parent', CategoryResource::collection(...)),
        ];
    }
}
