<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/** A model over the posts table with no primary key, so getKey() is getAttribute(null), always null. */
class NullKeyPost extends Model
{
    protected $table = 'posts';

    protected $primaryKey = null;
}
