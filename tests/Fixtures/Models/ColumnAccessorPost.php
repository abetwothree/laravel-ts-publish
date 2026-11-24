<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/** A test-only model on the `posts` table whose untyped getter returns its nullable `featured_image_url` column. */
class ColumnAccessorPost extends Model
{
    protected $table = 'posts';

    /** The featured image's URL, read through a getter that declares nothing. */
    protected function image(): Attribute
    {
        return Attribute::get(fn () => $this->featured_image_url);
    }
}
