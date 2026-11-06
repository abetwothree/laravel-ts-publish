<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Workbench\App\Models\Post;

/** A test-only post on the `posts` table that implements no interface, a bare arm beside a `Post&HasCode`. */
class SpecialPost extends Post
{
    protected $table = 'posts';
}
