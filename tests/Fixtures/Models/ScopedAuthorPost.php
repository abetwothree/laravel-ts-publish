<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Models\Post;

/** A post whose author is a user with a scope named like a `where…()` filter, with a scope and a method of its own. */
class ScopedAuthorPost extends Post
{
    protected $table = 'posts';

    /**
     * The author, as a user whose `whereSlim()` scope selects columns.
     *
     * @return BelongsTo<SlimScopedUser, $this>
     */
    public function slimAuthor(): BelongsTo
    {
        return $this->belongsTo(SlimScopedUser::class, 'user_id');
    }

    /** Hide the author, as a method of the application's own the analysis does not read. */
    public function hideAuthor(): static
    {
        return $this->makeHidden('author');
    }

    /**
     * Eager load the author with its key alone.
     *
     * @param  Builder<ScopedAuthorPost>  $query
     */
    public function scopeWithSlimAuthor(Builder $query): void
    {
        $query->with('author:id');
    }
}
