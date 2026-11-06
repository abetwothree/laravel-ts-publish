<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\BaseDeclaringCastable;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\NonNullBaseDeclaringCastable;
use Illuminate\Database\Eloquent\Model;

/** The `posts` table with columns cast to Castables that declare a base caster and return a serializing subclass. */
class BaseDeclaringCastablePost extends Model
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
            'title' => BaseDeclaringCastable::class,
            'options' => NonNullBaseDeclaringCastable::class,
        ];
    }
}
