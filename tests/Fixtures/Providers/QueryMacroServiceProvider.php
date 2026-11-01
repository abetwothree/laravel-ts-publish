<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Providers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\ServiceProvider;
use Workbench\App\Models\Comment;

/** Registers Eloquent builder macros that hand the query back, hand on another, or return a count, as boot() would. */
class QueryMacroServiceProvider extends ServiceProvider
{
    /**
     * A comment query, a method a macro names as its callable.
     *
     * @return Builder<Comment>
     */
    public static function commentQuery(): Builder
    {
        return Comment::query();
    }

    /** Register every macro on the Eloquent builder. */
    public function boot(): void
    {
        Builder::macro('newestFirst', function () {
            return $this->latest();
        });
        Builder::macro('titleLike', function ($term) {
            return $term ? $this->where('title', 'like', '%'.$term.'%') : $this;
        });
        Builder::macro('tally', function () {
            return $this->count();
        });
        Builder::macro('commentQuery', function () {
            return Comment::query();
        });
        Builder::macro('commentQueryFrom', [self::class, 'commentQuery']);
    }
}
