<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Workbench\App\Models\Post;

/** A test-only model on the `posts` table whose own `#[TsCasts]` types the key `k`. */
#[TsCasts(['k' => 'string | null'])]
class EnumResourceCastPost extends Post
{
    protected $table = 'posts';
}
