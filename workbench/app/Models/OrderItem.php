<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'name',
        'sku',
        'quantity',
        'unit_price',
        'total_price',
        'options',
    ];

    #[TsCasts(['options' => 'Record<string, string | number | boolean> | null'])]
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'options' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Line item subtotal computed from quantity × unit_price */
    protected function subtotal(): Attribute
    {
        return Attribute::make(
            get: fn (): float => (float) $this->quantity * (float) $this->unit_price,
        );
    }
}
