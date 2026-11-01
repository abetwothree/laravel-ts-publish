<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Workbench\App\Models\Post;

/** A post whose own toArray() writes its key alone, so no relation it loads is sent. */
class ArrayShapedPost extends Post
{
    protected $table = 'posts';

    /** @return array{id: mixed} */
    public function toArray(): array
    {
        return ['id' => $this->getKey()];
    }
}
