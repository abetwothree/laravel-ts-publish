<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;

/** A test-only post on the `posts` table with local scopes that hand the query back, or another, or a count. */
class ScopedPost extends Post
{
    protected $table = 'posts';

    /**
     * The newest post, a static the model declares itself.
     */
    public static function newest(): ?self
    {
        return static::query()->latest()->first();
    }

    /**
     * Posts with the given title, a scope declared void.
     *
     * @param  Builder<self>  $query
     */
    public function scopeTitled(Builder $query, string $title): void
    {
        $query->where('title', $title);
    }

    /**
     * Posts with an id, a scope that returns the query it is given with no declared type.
     *
     * @param  Builder<self>  $query
     */
    public function scopeIdentified($query)
    {
        return $query->whereNotNull('id');
    }

    /**
     * The number of posts, a scope that returns something other than the query.
     *
     * @param  Builder<self>  $query
     */
    public function scopeTallied($query)
    {
        return $query->count();
    }

    /**
     * Comments, a scope that hands on another model's query.
     *
     * @param  Builder<self>  $query
     */
    public function scopeRemarks($query)
    {
        return Comment::query();
    }

    /**
     * Posts as they are, a scope that returns null, which Laravel reads as the query.
     *
     * @param  Builder<self>  $query
     */
    public function scopeUnfiltered($query)
    {
        return null;
    }

    /**
     * The newest posts first, a scope the `#[Scope]` attribute marks.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function recent(Builder $query): void
    {
        $query->latest();
    }
}
