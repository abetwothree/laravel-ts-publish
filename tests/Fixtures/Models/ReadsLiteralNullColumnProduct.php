<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\ReadsLiteralNullColumnCast;
use Illuminate\Database\Eloquent\Model;

/**
 * The not-null `products.name` and the nullable `products.description` cast to a caster whose serialize() reads
 * LiteralNullCycleProduct's not-null name.
 */
class ReadsLiteralNullColumnProduct extends Model
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
            'name' => ReadsLiteralNullColumnCast::class,
            'description' => ReadsLiteralNullColumnCast::class,
        ];
    }
}
