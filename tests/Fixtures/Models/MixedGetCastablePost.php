<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\MixedGetLabelCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\MixedGetLabelCastable;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\OpaqueGetCastable;
use Illuminate\Database\Eloquent\Model;

/** The `posts` table with columns cast to serializing casters whose get() declares only `mixed`. */
class MixedGetCastablePost extends Model
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
            'title' => MixedGetLabelCastable::class,
            'content' => MixedGetLabelCast::class,
            'category' => OpaqueGetCastable::class,
        ];
    }
}
