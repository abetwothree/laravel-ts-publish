<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\AccessorCycleGetCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\AccessorCycleTypedGetCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\AccessorTypedGetCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\HelperCycleGetCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\HelperCycleTypedGetCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\OpaqueGetCast;
use Illuminate\Database\Eloquent\Model;

/**
 * The not-null `products` columns cast to serializing casters whose untyped get() returns no literal null, but may
 * read a value the package publishes with one: another model's accessor, a helper's model read or the raw value.
 */
class CarriedNullProduct extends Model
{
    protected $table = 'products';

    /**
     * Each column's cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => AccessorCycleTypedGetCast::class,
            'slug' => AccessorCycleGetCast::class,
            'sku' => HelperCycleTypedGetCast::class,
            'price' => HelperCycleGetCast::class,
            'quantity' => AccessorTypedGetCast::class,
            'is_active' => OpaqueGetCast::class,
        ];
    }
}
