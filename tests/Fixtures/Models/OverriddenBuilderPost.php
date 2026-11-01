<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Builders\PublishedPostBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Workbench\App\Models\Post;

/** A test-only post on the `posts` table whose `newEloquentBuilder()` override builds a custom Eloquent builder. */
class OverriddenBuilderPost extends Post
{
    protected $table = 'posts';

    /**
     * The custom builder over a base query.
     *
     * @param  QueryBuilder  $query
     * @return PublishedPostBuilder<static>
     */
    public function newEloquentBuilder($query): PublishedPostBuilder
    {
        return new PublishedPostBuilder($query);
    }
}
