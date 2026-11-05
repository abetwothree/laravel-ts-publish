<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Workbench\App\Models\Post;

/** A test-only post on the `posts` table that implements HasLabel, the runtime value of a `Post&HasLabel`. */
class LabelingPost extends Post implements HasLabel
{
    protected $table = 'posts';

    /** The title, prefixed. */
    public function label(): string
    {
        return 'L:'.$this->title;
    }
}
