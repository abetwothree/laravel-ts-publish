<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Workbench\App\Models\User;

/** An auth model identified by an enum-cast column, whose type names an enum no auth result can import. */
class EnumIdentifierUser extends User
{
    protected $table = 'users';

    /** Names the column the guard identifies the user by. */
    public function getAuthIdentifierName(): string
    {
        return 'role';
    }
}
