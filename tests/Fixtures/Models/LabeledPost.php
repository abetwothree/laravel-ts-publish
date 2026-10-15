<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Workbench\App\Models\Post;

/** A test-only post on the `posts` table whose label() declares no return type, so only its body types it. */
class LabeledPost extends Post
{
    protected $table = 'posts';

    /** The title in capitals. */
    public function label()
    {
        return strtoupper($this->title);
    }
}
