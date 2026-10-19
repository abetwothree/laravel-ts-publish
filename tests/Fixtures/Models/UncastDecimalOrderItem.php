<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A test-only model on the `order_items` table with no casts, not even its timestamps' date casts, so each column
 * publishes as its driver reads it.
 */
class UncastDecimalOrderItem extends Model
{
    public $timestamps = false;

    protected $table = 'order_items';
}
