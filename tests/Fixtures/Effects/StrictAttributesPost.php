<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects;

use Workbench\App\Models\Post;

/**
 * A post deciding by its environment whether reading a missing attribute throws, which Eloquent's getAttribute() asks
 * through `static::preventsAccessingMissingAttributes()`, so the call runs this override rather than Eloquent's.
 */
class StrictAttributesPost extends Post
{
    protected $table = 'posts';

    /**
     * Throws on a missing attribute only in a local environment.
     */
    public static function preventsAccessingMissingAttributes(): bool
    {
        return app()->isLocal();
    }
}
