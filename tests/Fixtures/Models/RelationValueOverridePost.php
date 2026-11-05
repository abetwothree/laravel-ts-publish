<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Workbench\App\Models\Post;

/** A post that overrides getRelationValue(), which Laravel's own getAttribute() reads a relation through. */
class RelationValueOverridePost extends Post
{
    protected $table = 'posts';

    /** Reads a relation as Laravel does. */
    public function getRelationValue($key)
    {
        return parent::getRelationValue($key);
    }
}
