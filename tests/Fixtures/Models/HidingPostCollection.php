<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Collection;

/**
 * A collection of posts whose own `first()` hides the post's author.
 *
 * @extends Collection<int, HidingCollectionPost>
 */
class HidingPostCollection extends Collection
{
    /** The first post, with its author hidden. */
    public function first(?callable $callback = null, $default = null)
    {
        return parent::first($callback, $default)?->makeHidden('author');
    }
}
