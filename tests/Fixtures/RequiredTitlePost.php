<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Workbench\App\Models\Post;

/** A test-only model on the `posts` table whose own `#[TsCasts]` marks `title` required. */
#[TsCasts(['title' => ['type' => 'string', 'optional' => false]])]
class RequiredTitlePost extends Post
{
    protected $table = 'posts';
}
