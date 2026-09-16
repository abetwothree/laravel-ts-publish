<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Workbench\App\Models\User;

/** A model whose only() override returns a list of users, whose columns name enums. */
final class UserListFilterOverrideModel extends Model
{
    protected $table = 'tags';

    /**
     * @param  array<int, string>|string  $attributes
     * @return array<int, User>
     */
    public function only($attributes): array
    {
        return [];
    }
}
