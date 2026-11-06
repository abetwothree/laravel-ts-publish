<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Support\Traits\Conditionable;
use Workbench\App\Models\Post;

/** A test-only post on the `posts` table that uses Conditionable itself, so its own when() runs the trait's. */
class ConditionablePost extends Post
{
    use Conditionable;

    protected $table = 'posts';
}
