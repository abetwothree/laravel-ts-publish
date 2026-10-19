<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/** An abstract model with no primary key, so getKey() is always null. */
abstract class AbstractNullKeyModel extends Model
{
    protected $primaryKey = null;
}
