<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\InertiaUiTable;

use Illuminate\Database\Eloquent\Builder;
use InertiaUI\Table\Table;
use Workbench\App\Models\Post;

class PostQueryTable extends Table
{
    /**
     * @return Builder<Post>
     */
    public function query(): Builder
    {
        return Post::query();
    }
}
