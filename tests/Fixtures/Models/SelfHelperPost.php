<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

/** A test-only post on the `posts` table, whose parent's helpers read its title. */
class SelfHelperPost extends SelfHelperBaseModel
{
    protected $table = 'posts';
}
