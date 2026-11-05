<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Workbench\App\Models\Post;

/** A post that overrides getAttribute(), so a read through it runs the override rather than Laravel's own. */
class AttributeOverridePost extends Post
{
    protected $table = 'posts';

    /** Reads an attribute as Laravel does. */
    public function getAttribute($key)
    {
        return parent::getAttribute($key);
    }
}
