<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Accessors whose types name a class, read by other models and resources through a closure parameter, a relation
 * chain or pluck(). `Comment` shares its name with a DOM global, so a missing import still compiles against the DOM.
 */
class Bulletin extends Model
{
    protected $table = 'posts';

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'post_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** A to-many relation's filter: a list of `Comment`. */
    protected function commentList(): Attribute
    {
        return Attribute::get(fn () => $this->comments->only([1, 2]));
    }

    /** A single relation's filter: a `Pick<User, …>`. */
    protected function authorPick(): Attribute
    {
        return Attribute::get(fn () => $this->author->only(['id', 'name']));
    }

    /** A filter on the model itself: a `Pick<Bulletin, …>`. */
    protected function ownPick(): Attribute
    {
        return Attribute::get(fn () => $this->only(['id', 'title']));
    }

    /** @return Attribute<User, never> */
    protected function owner(): Attribute
    {
        return Attribute::get(fn () => $this->author);
    }
}
