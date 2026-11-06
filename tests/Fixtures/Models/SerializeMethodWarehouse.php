<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\NoInterfaceSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\NoInterfaceTypedSerializeCast;
use Illuminate\Database\Eloquent\Model;

/** The nullable `warehouses` columns cast to casters with a serialize() method but not the interface. */
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
        return ['phone' => NoInterfaceSerializeCast::class, 'coordinate_data' => NoInterfaceTypedSerializeCast::class];
    }
}
