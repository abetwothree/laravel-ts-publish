<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Workbench\App\Models\User;

/** A user on the `users` table with a `title` accessor whose value no rule types. */
class AccessorTitleUser extends User
{
    protected $table = 'users';

    /** A title read through a value no rule types. */
    protected function title(): Attribute
    {
        return Attribute::make(get: fn () => unserialize(serialize(1)));
    }
}
