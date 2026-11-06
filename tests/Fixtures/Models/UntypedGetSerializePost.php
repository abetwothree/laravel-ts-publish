<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\DocblockGetObjectSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\OpaqueGetSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\UntypedGetSerializeCast;
use Illuminate\Database\Eloquent\Model;

/**
 * The not-null `posts` columns cast to serializing casters whose get() declares no native type: its body proves a
 * string, its `@return` documents a number, or its body proves nothing.
 */
class UntypedGetSerializePost extends Model
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
            'title' => UntypedGetSerializeCast::class,
            'content' => DocblockGetObjectSerializeCast::class,
            'category' => OpaqueGetSerializeCast::class,
        ];
    }
}
