<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Concerns\SearchesLikeScout;
use Workbench\App\Models\Post;

/** A test-only post on the `posts` table searched through a builder Eloquent does not define. */
class SearchedPost extends Post
{
    use SearchesLikeScout;

    protected $table = 'posts';
}
