<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Model;

/** A model spelling its key type `integer`, which Laravel casts exactly like `int`. */
final class ReceiverIntegerKeyModel extends Model
{
    protected $table = 'posts';

    protected $keyType = 'integer';
}
