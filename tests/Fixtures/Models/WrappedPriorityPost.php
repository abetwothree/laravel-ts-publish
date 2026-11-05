<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Workbench\App\Models\Post;

/** A test-only post on the `posts` table whose `priority_wrap` accessor wraps its nullable priority in a resource. */
class WrappedPriorityPost extends Post
{
    protected $table = 'posts';

    /** The priority, wrapped. */
    protected function priorityWrap(): Attribute
    {
        return Attribute::get(fn () => EnumResource::make($this->priority));
    }
}
