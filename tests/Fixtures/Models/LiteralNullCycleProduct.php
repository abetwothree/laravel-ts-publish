<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\LiteralNullCycleGetSerializeCast;
use Illuminate\Database\Eloquent\Model;

/**
 * The not-null `products.name` cast to a caster whose get() returns a literal null and reads through a cycle.
 */
class LiteralNullCycleProduct extends Model
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
            'name' => LiteralNullCycleGetSerializeCast::class,
        ];
    }
}
