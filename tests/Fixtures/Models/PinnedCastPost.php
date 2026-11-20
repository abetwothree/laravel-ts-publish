<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Workbench\App\Models\Post;

/** A test-only model on the `posts` table whose own `#[TsCasts]` types `is_pinned`, a key a resource's arms follow. */
#[TsCasts(['is_pinned' => 'boolean'])]
class PinnedCastPost extends Post
{
    protected $table = 'posts';
}
