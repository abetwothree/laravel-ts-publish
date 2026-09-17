<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Appends accessors whose types name a class, so a resource that spreads this model's toArray() publishes them:
 * a list of `Comment`, a `Pick<User, …>` and a `Pick<BulletinDigest, …>`.
 */
class BulletinDigest extends Model
{
    protected $table = 'posts';

    protected $appends = ['comment_list', 'author_pick', 'own_pick'];

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

    /** @return BelongsTo<BulletinOwnership, $this> */
    public function ownership(): BelongsTo
    {
        return $this->belongsTo(BulletinOwnership::class, 'user_id');
    }

    protected function commentList(): Attribute
    {
        return Attribute::get(fn () => $this->comments->only([1, 2]));
    }

    protected function authorPick(): Attribute
    {
        return Attribute::get(fn () => $this->author->only(['id', 'name']));
    }

    protected function ownPick(): Attribute
    {
        return Attribute::get(fn () => $this->only(['id', 'title']));
    }
}
