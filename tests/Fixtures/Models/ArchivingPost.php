<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Scopes\HidesArchived;
use Workbench\App\Models\Post;

/** A test-only post on the `posts` table whose global scope adds a local `withArchived()` macro to its queries. */
class ArchivingPost extends Post
{
    protected $table = 'posts';

    /**
     * Add the global scope.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new HidesArchived);
    }
}
