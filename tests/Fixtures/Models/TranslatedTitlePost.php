<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Concerns\TranslatesTitle;
use Workbench\App\Models\Post;

/** A post whose trait overrides getAttributeValue(), which Laravel's own getAttribute() reads through. */
class TranslatedTitlePost extends Post
{
    use TranslatesTitle;

    protected $table = 'posts';
}
