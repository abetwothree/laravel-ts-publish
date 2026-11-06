<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\LabelInterfaceSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\LabelMethodSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\MixedGetMethodSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\MixedStubGetSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\NoInterfaceTypedSerializeCast;
use Illuminate\Database\Eloquent\Model;

/**
 * The not-null `posts` columns cast to casters with a serialize() method, which Model::toArray() writes while a read
 * off the model sends get()'s value: a label get() without and with SerializesCastableAttributes, a number get()
 * without it, and a get() that declares only `mixed` with and without it.
 */
class LabelSerializePost extends Model
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
            'title' => LabelMethodSerializeCast::class,
            'content' => LabelInterfaceSerializeCast::class,
            'category' => MixedStubGetSerializeCast::class,
            'user_id' => NoInterfaceTypedSerializeCast::class,
            'status' => MixedGetMethodSerializeCast::class,
        ];
    }
}
