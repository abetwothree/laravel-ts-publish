<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Workbench\App\Models\User;

/** A user whose `$visible` names one column and one relation by its method name, so toArray() writes those alone. */
class RelationVisibilityUser extends User
{
    protected $table = 'users';

    protected $visible = ['id', 'ownedTeams'];
}
