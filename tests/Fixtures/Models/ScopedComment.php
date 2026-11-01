<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Builder;
use Workbench\App\Models\Comment;

/** A test-only comment on the `comments` table with a scope a relation reaches. */
class ScopedComment extends Comment
{
    protected $table = 'comments';

    /**
     * Comments reading `alpha`.
     *
     * @param  Builder<self>  $query
     */
    public function scopeAlpha(Builder $query): void
    {
        $query->where('content', 'alpha');
    }
}
