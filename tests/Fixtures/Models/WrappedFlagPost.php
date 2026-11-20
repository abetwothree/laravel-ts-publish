<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\WrappedFlagCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\WrappedFlagValueCast;
use Workbench\App\Models\Post;

/**
 * A post whose boolean columns read as objects typed `boolean`: `is_pinned` through a cast's own override, `status`
 * through a cast returning a value class with one.
 */
class WrappedFlagPost extends Post
{
    protected $table = 'posts';

    protected function casts(): array
    {
        return [...parent::casts(), 'is_pinned' => WrappedFlagCast::class, 'status' => WrappedFlagValueCast::class];
    }
}
