<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\ArraySerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\ArraySerializeInterfaceCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\MixedSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\MixedSerializeInterfaceCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\NoInterfaceSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\NoInterfaceTypedSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\ShapeArraySerializeCast;
use Illuminate\Database\Eloquent\Model;

/**
 * The nullable `warehouses` columns cast to casters with a serialize() method, most without the interface, some whose
 * serialize() declares only `mixed` or `array`.
 */
class SerializeMethodWarehouse extends Model
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
            'phone' => NoInterfaceSerializeCast::class,
            'coordinate_data' => NoInterfaceTypedSerializeCast::class,
            'status' => MixedSerializeCast::class,
            'color' => MixedSerializeInterfaceCast::class,
            'priority' => ArraySerializeCast::class,
            'manager_id' => ShapeArraySerializeCast::class,
            'primary_contact_id' => ArraySerializeInterfaceCast::class,
        ];
    }
}
