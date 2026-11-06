<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\ArrayOrObjectSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\NullableObjectSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\NumberObjectSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\ObjectSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\PrefixedMixedSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\StringArraySerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\StringListSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\LooselySerializedTally;
use Illuminate\Database\Eloquent\Model;

/**
 * The nullable `warehouses` columns cast to casters whose serialize() declares only `mixed`, `object`, `array` or a
 * union of them, over a get() that returns an object or a scalar.
 */
class VagueSerializeWarehouse extends Model
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
            'phone' => PrefixedMixedSerializeCast::class,
            'coordinate_data' => ObjectSerializeCast::class,
            'status' => NullableObjectSerializeCast::class,
            'color' => ArrayOrObjectSerializeCast::class,
            'priority' => NumberObjectSerializeCast::class,
            'manager_id' => StringArraySerializeCast::class,
            'primary_contact_id' => LooselySerializedTally::class,
            'secondary_contact_id' => StringListSerializeCast::class,
        ];
    }
}
