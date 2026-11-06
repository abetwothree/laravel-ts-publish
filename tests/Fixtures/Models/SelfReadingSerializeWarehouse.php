<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\SelfReadingSerializeCast;
use Illuminate\Database\Eloquent\Model;

/**
 * The not-null `warehouses.name` cast to a caster whose serialize() reads this same attribute.
 */
class SelfReadingSerializeWarehouse extends Model
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
            'name' => SelfReadingSerializeCast::class,
        ];
    }
}
