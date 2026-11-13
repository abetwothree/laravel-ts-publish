<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * A warehouse whose phone accessor falls back to its uncast name.
 */
class AccessorWarehouse extends Model
{
    protected $table = 'warehouses';

    /**
     * The stored phone, or the name where none is stored.
     */
    protected function phone(): Attribute
    {
        return Attribute::get(fn ($value) => is_string($value) ? $value : $this->name);
    }
}
