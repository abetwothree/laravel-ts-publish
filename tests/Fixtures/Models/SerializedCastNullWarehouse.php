<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\BlankToNullSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\InboundDualCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\NullableSerializedLabelCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\SerializedLabelCast;
use Illuminate\Database\Eloquent\Model;

/**
 * The nullable `warehouses` columns cast to classes whose non-null get() does not decide what toArray() sends: a
 * serializing caster's serialize() does, and a caster that is inbound too is never read.
 */
class SerializedCastNullWarehouse extends Model
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
            'phone' => BlankToNullSerializeCast::class,
            'coordinate_data' => InboundDualCast::class,
            'status' => SerializedLabelCast::class,
            'color' => NullableSerializedLabelCast::class,
        ];
    }
}
