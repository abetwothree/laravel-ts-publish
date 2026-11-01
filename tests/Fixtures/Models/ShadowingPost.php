<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Workbench\App\Models\Post;

/** A test-only post on the `posts` table whose own statics share names with query terminals. */
class ShadowingPost extends Post
{
    protected $table = 'posts';

    /**
     * A number of the model's own, named like the query's cursor().
     */
    public static function cursor(): int
    {
        return 7;
    }

    /**
     * A label of the model's own, named like the query's value().
     */
    public static function value(string $key): string
    {
        return 'own-'.$key;
    }
}
