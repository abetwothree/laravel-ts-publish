<?php

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Product;

/**
 * Exercises: multiple whenAggregated (sum/min/max), whenNotNull, when,
 * whenCounted, two mergeWhen blocks, Resource::collection x2.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'description' => $this->description,
            'price' => $this->price,
            'compare_at_price' => $this->whenNotNull($this->compare_at_price),
            'cost_price' => $this->when($request->user()?->isAdmin(), $this->cost_price),
            'quantity' => $this->quantity,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'published_at' => $this->whenNotNull($this->published_at),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'images' => ImageResource::collection($this->whenLoaded('images')),
            'orders_count' => $this->whenCounted('orderItems'),
            'total_sold' => $this->whenAggregated('orderItems', 'quantity', 'sum'),
            'min_unit_price' => $this->whenAggregated('orderItems', 'unit_price', 'min'),
            'max_unit_price' => $this->whenAggregated('orderItems', 'unit_price', 'max'),
            $this->mergeWhen($this->is_featured, [
                'weight' => $this->weight,
                'dimensions' => $this->dimensions,
            ]),
            $this->mergeWhen($request->user()?->isAdmin(), [
                'metadata' => $this->metadata,
            ]),
        ];
    }
}
