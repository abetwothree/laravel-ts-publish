<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\BareReturnGetSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\FallOffGetSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\MixedDocNullGetSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\MixedNullGetSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\NullableDocGetSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\TernaryNullGetSerializeCast;
use Illuminate\Database\Eloquent\Model;

/**
 * The not-null `products` columns cast to serializing casters whose get() provably returns null: a ternary arm, a bare
 * `return;`, a fall-off or a documented `int|null`, and a ternary arm under a `mixed` native or documented type.
 */
class NullReturningGetProduct extends Model
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
            'name' => TernaryNullGetSerializeCast::class,
            'slug' => BareReturnGetSerializeCast::class,
            'sku' => FallOffGetSerializeCast::class,
            'price' => NullableDocGetSerializeCast::class,
            'quantity' => MixedDocNullGetSerializeCast::class,
            'is_active' => MixedNullGetSerializeCast::class,
        ];
    }
}
