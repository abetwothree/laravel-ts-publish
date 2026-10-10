<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\ArgumentCastable;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\CountingCastable;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\DeclaredCastable;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\InstanceCastable;
use Illuminate\Database\Eloquent\Model;

/** A test-only model on the `posts` table, each column cast by a Castable that names its caster a different way. */
class CastablePost extends Model
{
    protected $table = 'posts';

    /**
     * Each column's Castable.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'title' => CountingCastable::class,
            'content' => InstanceCastable::class,
            'metadata' => DeclaredCastable::class,
            'options' => ArgumentCastable::class,
        ];
    }
}
