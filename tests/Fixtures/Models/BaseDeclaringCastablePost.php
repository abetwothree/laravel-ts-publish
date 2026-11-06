<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\BaseDeclaringCastable;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\NonNullBaseDeclaringCastable;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\PropertiedLabelCastable;
use Illuminate\Database\Eloquent\Model;

/** The `posts` table with columns cast to Castables that are not the value their serializing casters read. */
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
            'content' => PropertiedLabelCastable::class,
        ];
    }
}
