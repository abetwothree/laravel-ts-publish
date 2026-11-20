<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects;

use Workbench\App\Models\Post;

/**
 * A post overriding Eloquent's getKey() with one that clears its pin first.
 */
class KeyWritingPost extends Post
{
    protected $table = 'posts';

    /**
     * Clears the pin, then reads the key as Eloquent does.
     */
    public function getKey(): mixed
    {
        $this->is_pinned = false;

        return parent::getKey();
    }
}
