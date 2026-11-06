<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\EnumGetArraySerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\MixedGetArraySerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\UnionGetArraySerializeCast;
use Illuminate\Database\Eloquent\Model;

/**
 * The not-null `posts` columns cast to casters without SerializesCastableAttributes whose serialize() writes an array
 * over a get() that returns no object or array: an enum, `mixed`, or a string or a label.
 */
class ArraySerializePost extends Model
{
    protected $table = 'posts';

    /**
     * Each column's cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'title' => EnumGetArraySerializeCast::class,
            'content' => MixedGetArraySerializeCast::class,
            'category' => UnionGetArraySerializeCast::class,
        ];
    }
}
