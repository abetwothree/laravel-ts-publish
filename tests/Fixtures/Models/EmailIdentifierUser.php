<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Workbench\App\Models\User;

/** An auth model identified by its email column, so Auth::id() sends the email and not the key. */
class EmailIdentifierUser extends User
{
    /** Names the column the guard identifies the user by. */
    public function getAuthIdentifierName(): string
    {
        return 'email';
    }
}
