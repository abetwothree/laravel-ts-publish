<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\InertiaUiTable;

use InertiaUI\Table\Table;
use Workbench\App\Models\Post;

class PostTable extends Table
{
    protected ?string $resource = Post::class;
}
