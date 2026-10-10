<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Workbench\App\Models\Post;

/** A post whose model-level cast types `main_tag`, a key a resource's `_tag` signature covers. */
#[TsCasts(['main_tag' => 'number'])]
class TagCastPost extends Post
{
    protected $table = 'posts';
}
