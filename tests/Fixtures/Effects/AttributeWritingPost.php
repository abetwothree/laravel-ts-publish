<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects;

use Workbench\App\Models\Post;

/**
 * A soft-deleting post overriding getAttribute() with one that clears its pin first, so the SoftDeletes trait's
 * trashed(), whose `$this->{$column}` read runs Eloquent's `__get()`, writes.
 */
class AttributeWritingPost extends Post
{
    protected $table = 'posts';

    /**
     * Clears the pin, then reads the attribute as Eloquent does.
     */
    public function getAttribute($key): mixed
    {
        $this->attributes['is_pinned'] = false;

        return parent::getAttribute($key);
    }
}
