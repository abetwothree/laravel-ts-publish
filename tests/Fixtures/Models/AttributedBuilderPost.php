<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Builders\PublishedPostBuilder;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Workbench\App\Models\Post;

/** A test-only post on the `posts` table whose `#[UseEloquentBuilder]` names a custom Eloquent builder. */
#[UseEloquentBuilder(PublishedPostBuilder::class)]
class AttributedBuilderPost extends Post
{
    protected $table = 'posts';
}
