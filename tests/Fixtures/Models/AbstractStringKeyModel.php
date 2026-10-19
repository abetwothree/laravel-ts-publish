<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/** An abstract model declaring a string key, which getKey() publishes without an instance. */
abstract class AbstractStringKeyModel extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;
}
