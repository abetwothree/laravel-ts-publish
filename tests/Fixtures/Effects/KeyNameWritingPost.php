<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects;

use Workbench\App\Models\Post;

/**
 * A post overriding getKeyName() with one that clears its pin first, so Eloquent's getKey(), which calls it, writes.
 */
class KeyNameWritingPost extends Post
{
    protected $table = 'posts';

    /**
     * Clears the pin, then names the key as Eloquent does.
     */
    public function getKeyName(): string
    {
        $this->is_pinned = false;

        return parent::getKeyName();
    }
}
