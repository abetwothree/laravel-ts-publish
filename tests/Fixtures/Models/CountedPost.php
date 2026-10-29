<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Countable;
use Workbench\App\Models\Post;

/** A test-only post on the `posts` table that is also Countable, an interface Post does not implement. */
class CountedPost extends Post implements Countable
{
    protected $table = 'posts';

    /**
     * The post's word count.
     */
    public function count(): int
    {
        return (int) $this->word_count;
    }
}
