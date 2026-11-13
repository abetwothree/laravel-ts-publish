<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\LabelColumnCastable;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\NullableGetReturnTypedSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\NullableGetSerializedLabelCast;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * The not-null `products` name, slug and sku and the nullable description, compare_at_price and cost_price, a pair
 * each cast to a typed serialize(), one typed by its returns, and a Castable, every get() returning null for a NULL.
 */
class NotNullSerializeProduct extends Model
{
    use HasUuids;

    protected $table = 'products';

    protected $guarded = [];

    /**
     * Each column's cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => NullableGetSerializedLabelCast::class,
            'description' => NullableGetSerializedLabelCast::class,
            'slug' => NullableGetReturnTypedSerializeCast::class,
            'compare_at_price' => NullableGetReturnTypedSerializeCast::class,
            'sku' => LabelColumnCastable::class,
            'cost_price' => LabelColumnCastable::class,
        ];
    }
}
