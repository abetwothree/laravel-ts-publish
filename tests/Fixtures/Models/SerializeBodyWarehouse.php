<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\NullableGetReturnTypedSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\NullableGetSerializedLabelCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\ReturnTypedSerializeCast;
use Illuminate\Database\Eloquent\Model;

/**
 * The `warehouses` columns cast to serializing casters whose serialize() declares nothing or whose get() may return
 * null: the not-null `name` and the nullable `phone` and `coordinate_data`.
 */
class SerializeBodyWarehouse extends Model
{
    protected $table = 'warehouses';

    /**
     * Each column's cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => NullableGetSerializedLabelCast::class,
            'phone' => ReturnTypedSerializeCast::class,
            'coordinate_data' => NullableGetReturnTypedSerializeCast::class,
        ];
    }
}
