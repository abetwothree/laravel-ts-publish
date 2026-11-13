<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\AbortingGetSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\LoopingGetSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\ThrowingMatchGetSerializeCast;
use Illuminate\Database\Eloquent\Model;

/**
 * The not-null `posts` columns cast to serializing casters whose untyped get() can never run to its end: it ends in
 * abort(), loops until it returns, or ends in a match whose every arm throws.
 */
class NeverEndingGetPost extends Model
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
            'title' => AbortingGetSerializeCast::class,
            'content' => LoopingGetSerializeCast::class,
            'category' => ThrowingMatchGetSerializeCast::class,
        ];
    }
}
