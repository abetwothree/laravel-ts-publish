<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\CycleReadingSerializeCast;
use Illuminate\Database\Eloquent\Model;

/**
 * The not-null `warehouses.name` cast to a caster whose serialize() reads SelfCycleWarehouse's phone.
 */
class CycleSerializeWarehouse extends Model
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
            'name' => CycleReadingSerializeCast::class,
        ];
    }
}
