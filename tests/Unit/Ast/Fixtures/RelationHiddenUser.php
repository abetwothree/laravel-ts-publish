<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Workbench\App\Models\User;

/** A user whose `$hidden` lists a relation by its method name, so toArray() never writes it. */
class RelationHiddenUser extends User
{
    protected $table = 'users';

    protected $hidden = ['password', 'remember_token', 'ownedTeams'];
}
