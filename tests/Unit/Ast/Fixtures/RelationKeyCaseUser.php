<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Workbench\App\Models\User;

/** A user whose toArray() keeps relation keys as their method names, since `$snakeAttributes` is off. */
class RelationKeyCaseUser extends User
{
    public static $snakeAttributes = false;

    protected $table = 'users';
}
