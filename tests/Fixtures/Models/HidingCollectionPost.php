<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Workbench\App\Models\Post;

/** A post collected into a collection whose own `first()` hides the author. */
class HidingCollectionPost extends Post
{
    protected $table = 'posts';

    /**
     * Collect posts into the collection that hides the first one's author.
     *
     * @param  array<array-key, HidingCollectionPost>  $models
     */
    public function newCollection(array $models = []): HidingPostCollection
    {
        return new HidingPostCollection($models);
    }
}
