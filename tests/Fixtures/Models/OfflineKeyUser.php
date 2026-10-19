<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Workbench\App\Models\User;

/** An auth model on a connection the publish run cannot reach, so it is never inspected. */
class OfflineKeyUser extends User
{
    protected $connection = 'unreachable';
}
