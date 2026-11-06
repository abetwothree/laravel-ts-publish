<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/** An abstract model over the `posts` table that declares no label, which an intersection pairs with HasLabel. */
abstract class AbstractEntry extends Model
{
    protected $table = 'posts';

    protected $guarded = [];
}
