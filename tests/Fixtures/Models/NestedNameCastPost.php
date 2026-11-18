<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;
use Workbench\App\Models\Post;

/**
 * A test-only post on the `posts` table whose cast and extends clause, each with an import, only build on a name.
 */
#[TsCasts(['title' => ['type' => 'Paginated<UserResource>', 'import' => '@/types/paginated']])]
#[TsExtends('Pick<UserResource, "id">', '@/types/user')]
class NestedNameCastPost extends Post
{
    protected $table = 'posts';
}
