<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Workbench\App\Models\Comment;

/** A test-only comment on the `comments` table whose content casts to an integer. */
class NumberedComment extends Comment
{
    protected $table = 'comments';

    /**
     * The comment's casts, with its content read as an integer.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [...parent::casts(), 'content' => 'integer'];
    }
}
