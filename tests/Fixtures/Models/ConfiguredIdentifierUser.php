<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Workbench\App\Models\User;

/** An auth model whose identifier column comes from config, which only running the application can tell. */
class ConfiguredIdentifierUser extends User
{
    protected $table = 'users';

    /** Names the column the guard identifies the user by, as configured. */
    public function getAuthIdentifierName(): string
    {
        return config('auth.identifier_column', 'email');
    }
}
