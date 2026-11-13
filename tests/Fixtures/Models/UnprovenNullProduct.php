<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\LiteralNullCycleBodyCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\MemoizingGetCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\MemoizingTypedGetCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\MutualHelperGetCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\PropertyFallbackGetCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\RecursiveHelperGetCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\RecursiveHelperTypedGetCast;
use Illuminate\Database\Eloquent\Model;

/**
 * The not-null `products` columns cast to serializing casters whose untyped get() returns a literal null for a NULL,
 * read through a caster cycle, a recursive or mutual helper, a property fallback or a `??=` the engine cannot read.
 */
class UnprovenNullProduct extends Model
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
            'name' => LiteralNullCycleBodyCast::class,
            'slug' => RecursiveHelperGetCast::class,
            'sku' => RecursiveHelperTypedGetCast::class,
            'price' => MutualHelperGetCast::class,
            'quantity' => PropertyFallbackGetCast::class,
            'is_active' => MemoizingGetCast::class,
            'is_featured' => MemoizingTypedGetCast::class,
        ];
    }
}
