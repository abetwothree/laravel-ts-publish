<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'sku',
        'description',
        'price',
        'compare_at_price',
        'cost_price',
        'quantity',
        'weight',
        'dimensions',
        'is_active',
        'is_featured',
        'published_at',
        'metadata',
    ];

    #[TsCasts([
        'dimensions' => '{ length: number; width: number; height: number; unit: "cm" | "in" }',
        'metadata' => [
            'type' => 'ProductMetadata | ProductJsonMetaData | null',
            'import' => '@js/types/product',
        ],
    ])]
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'quantity' => 'integer',
            'weight' => 'float',
            'dimensions' => 'array',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Polymorphic many-to-many with tags */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    /** Polymorphic one-to-many with images */
    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    /** Whether the product is on sale */
    protected function isOnSale(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->compare_at_price !== null && $this->compare_at_price > $this->price,
        );
    }

    /** Discount percentage (0-100) or null */
    protected function discountPercentage(): Attribute
    {
        return Attribute::make(
            get: fn (): ?float => $this->compare_at_price && $this->compare_at_price > 0
                ? round((1 - ($this->price / $this->compare_at_price)) * 100, 1)
                : null,
        );
    }

    /** Profit margin percentage */
    protected function profitMargin(): Attribute
    {
        return Attribute::make(
            get: fn (): ?float => $this->cost_price && $this->cost_price > 0
                ? round((($this->price - $this->cost_price) / $this->price) * 100, 1)
                : null,
        );
    }

    /** Whether the product is in stock */
    protected function inStock(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->quantity > 0,
        );
    }
}
