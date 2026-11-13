<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Foundation\Auth\User as Authenticatable;

/** A second auth model on the users table whose `name` reads as its length, so a type from another model shows. */
class NameLengthMember extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];

    /** Reads the name as its length. */
    protected function name(): Attribute
    {
        return Attribute::get(fn (?string $value): int => strlen((string) $value));
    }
}
