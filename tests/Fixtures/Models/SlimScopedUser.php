<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Builder;
use Workbench\App\Models\User;

/** A user with a local scope named like a `where…()` filter that selects two columns instead. */
class SlimScopedUser extends User
{
    protected $table = 'users';

    /**
     * Select the key and the name alone.
     *
     * @param  Builder<SlimScopedUser>  $query
     */
    public function scopeWhereSlim(Builder $query): void
    {
        $query->select('id', 'name');
    }
}
