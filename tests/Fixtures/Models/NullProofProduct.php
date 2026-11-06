<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\AttributeDefaultGetSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\CoalesceGetSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\ElvisGetSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\MixedDocGetSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\MixedStubGetSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\OpaqueGetSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\SelfReadingGetSerializeCast;
use Illuminate\Database\Eloquent\Model;

/**
 * The not-null `products` columns cast to serializing casters whose get() never provably returns null: a `mixed`
 * native or documented type, a body replacing a NULL, a body reading its own cast, or one the engine cannot read.
 */
class NullProofProduct extends Model
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
            'name' => MixedStubGetSerializeCast::class,
            'slug' => MixedDocGetSerializeCast::class,
            'sku' => CoalesceGetSerializeCast::class,
            'price' => ElvisGetSerializeCast::class,
            'quantity' => AttributeDefaultGetSerializeCast::class,
            'is_active' => SelfReadingGetSerializeCast::class,
            'is_featured' => OpaqueGetSerializeCast::class,
        ];
    }
}
