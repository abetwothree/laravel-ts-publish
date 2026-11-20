<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Workbench\App\Models\Post;

/** A test-only post on the `posts` table whose cast, with an import, builds on a resource another key sends. */
#[TsCasts(['title' => ['type' => 'Paginated<UserResource>', 'import' => '@/types/meta']])]
class SiblingCastPost extends Post
{
    protected $table = 'posts';
}
