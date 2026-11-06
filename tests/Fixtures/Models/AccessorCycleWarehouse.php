<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\AccessorReadingSerializeCast;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * A warehouse whose phone accessor falls back to its name, cast to a caster whose serialize() reads that accessor back.
 */
class AccessorCycleWarehouse extends Model
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
            'name' => AccessorReadingSerializeCast::class,
        ];
    }

    /**
     * The stored phone, or the name where none is stored.
     */
    protected function phone(): Attribute
    {
        return Attribute::get(fn ($value) => is_string($value) ? $value : $this->name);
    }
}
