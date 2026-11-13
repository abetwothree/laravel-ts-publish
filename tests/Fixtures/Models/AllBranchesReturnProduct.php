<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\IfElseReturnsGetSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\SwitchReturnsGetSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\TryReturnsGetSerializeCast;
use Illuminate\Database\Eloquent\Model;

/**
 * The not-null `products` columns cast to serializing casters whose untyped get() returns on every branch of an
 * if/else, a switch with a default, or a try and its catch, so it never falls off its end.
 */
class AllBranchesReturnProduct extends Model
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
            'name' => IfElseReturnsGetSerializeCast::class,
            'slug' => SwitchReturnsGetSerializeCast::class,
            'sku' => TryReturnsGetSerializeCast::class,
        ];
    }
}
