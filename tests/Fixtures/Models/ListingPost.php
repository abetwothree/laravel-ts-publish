<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services\OpaqueQueries;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;

/** A test-only post on the `posts` table whose own methods list posts, find one, or query through a service. */
class ListingPost extends Post
{
    protected $table = 'posts';

    /**
     * The newest posts, with no declared return.
     */
    public static function popular()
    {
        return static::query()->latest()->take(5)->get();
    }

    /**
     * The newest posts, declared as a collection naming no model.
     */
    public static function popularTyped(): Collection
    {
        return static::query()->latest()->take(5)->get();
    }

    /**
     * The newest posts, documented as a collection of the model.
     *
     * @return Collection<int, static>
     */
    public static function popularDoc()
    {
        return static::query()->latest()->take(5)->get();
    }

    /**
     * The newest post, with no declared return.
     */
    public static function newest()
    {
        return static::query()->latest()->first();
    }

    /**
     * Every post, or the first one.
     */
    public static function either(bool $many)
    {
        return $many ? static::query()->get() : static::query()->first();
    }

    /**
     * Every post, or every comment.
     */
    public static function postsOrComments(bool $posts)
    {
        return $posts ? static::query()->get() : Comment::query()->get();
    }

    /**
     * Rows read through a scope a service answers, held in a local first.
     */
    public static function rowsVia()
    {
        $query = static::query()->sameVia();

        return $query->get();
    }

    /**
     * Every post, with no declared return.
     */
    public function siblings()
    {
        return static::query()->get();
    }

    /**
     * The query a service hands back as it is given, which the engine cannot read.
     *
     * @param  Builder<self>  $query
     */
    public function scopeSameVia($query)
    {
        return app(OpaqueQueries::class)->same($query);
    }

    /**
     * Another model's query a service returns, which the engine cannot read.
     *
     * @param  Builder<self>  $query
     */
    public function scopeCommentsVia($query)
    {
        return app(OpaqueQueries::class)->comments($query);
    }
}
