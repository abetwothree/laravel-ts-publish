<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\CycleReadingGetSerializeCast;
use Illuminate\Database\Eloquent\Model;

/**
 * The not-null `products.name` cast to a caster whose get() reads through a serialize() that reads this attribute.
 */
class CycleReadingGetProduct extends Model
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
            'name' => CycleReadingGetSerializeCast::class,
        ];
    }
}
