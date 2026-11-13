<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Workbench\App\Models\User;

/** A user on the `users` table whose `getAttribute()` answers a `title` the model does not list. */
class AttributeTitleUser extends User
{
    protected $table = 'users';

    /**
     * Read `title` as a number, any other key as a model does.
     *
     * @param  string  $key
     */
    public function getAttribute($key): mixed
    {
        return $key === 'title' ? 456 : parent::getAttribute($key);
    }
}
