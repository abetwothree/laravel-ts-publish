<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Workbench\App\Models\Image;

/** An image that appends an accessor with no annotation, whose getter body is all that types it. */
class AppendingImage extends Image
{
    protected $table = 'images';

    protected $appends = ['no_docblock_accessor'];
}
