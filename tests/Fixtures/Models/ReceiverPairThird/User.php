<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ReceiverPairThird;

use Illuminate\Database\Eloquent\Model;

/** A test-only third model named `User`, on the `users` table, beside the application's and the CRM's. */
class User extends Model
{
    protected $table = 'users';
}
