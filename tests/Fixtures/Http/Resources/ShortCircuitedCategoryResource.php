<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\CategoryResource;
use Workbench\App\Models\Category;

/**
 * toResource() after a `?->` earlier in its chain: a null parent ends the whole chain as null, while a parent with no
 * parent of its own makes the arrow call throw. A local or a collection() argument ends the chain: null throws there.
 *
 * @mixin Category
 */
class ShortCircuitedCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $grandparent = $this->parent?->parent;

        return [
            'grandparent' => $this->parent?->parent->toResource(),
            'grandparent_proxy' => $this->resource->parent?->parent->toResource(),
            'grandparent_explicit' => $this->parent?->parent->toResource(CategoryResource::class),
            'local_grandparent' => $grandparent->toResource(),
            'siblings_static' => CategoryResource::collection($this->parent?->children),
        ];
    }
}
