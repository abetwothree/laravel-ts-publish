<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Tables;

use Illuminate\Database\Eloquent\Builder;
use InertiaUI\Table\Table;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/** A table naming one model by `$resource` whose `query()` is a query on another. */
class UserQueryPostTable extends Table
{
    protected ?string $resource = Post::class;

    /**
     * @return Builder<User>
     */
    public function query(): Builder
    {
        return User::query();
    }
}
