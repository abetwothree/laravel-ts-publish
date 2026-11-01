<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Workbench\App\Models\Post;

/** A post whose `$with` loads its author with two columns, and its category's posts with one. */
class EagerColumnsPost extends Post
{
    protected $table = 'posts';

    protected $with = ['author:id,name', 'categoryRel.posts:id'];
}
