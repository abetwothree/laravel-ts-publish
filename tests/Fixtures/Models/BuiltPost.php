<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Builders\PublishedPostBuilder;
use Workbench\App\Models\Post;

/** A test-only post on the `posts` table whose `$builder` names a custom Eloquent builder. */
class BuiltPost extends Post
{
    protected static string $builder = PublishedPostBuilder::class;

    protected $table = 'posts';
}
