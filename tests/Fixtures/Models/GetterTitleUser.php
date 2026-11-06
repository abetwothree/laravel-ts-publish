<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Workbench\App\Models\User;

/** A user on the `users` table with an untyped `getTitleAttribute()` getter whose value no rule types. */
class GetterTitleUser extends User
{
    protected $table = 'users';

    /** A title read through a value no rule types. */
    public function getTitleAttribute()
    {
        return unserialize(serialize(1));
    }
}
