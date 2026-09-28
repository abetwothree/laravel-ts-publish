<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Reads Bulletin's accessors through a relation chain and an untyped closure parameter. */
class BulletinFeed extends Model
{
    protected $table = 'posts';

    /** @return HasMany<Bulletin, $this> */
    public function bulletins(): HasMany
    {
        return $this->hasMany(Bulletin::class, 'user_id');
    }

    /** @return BelongsTo<Bulletin, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Bulletin::class, 'user_id');
    }

    protected function leadComments(): Attribute
    {
        return Attribute::get(fn () => $this->lead->comment_list);
    }

    /** Bulletin's owner is typed by its `Attribute<User, never>` docblock. */
    protected function owners(): Attribute
    {
        return Attribute::get(fn () => $this->bulletins->map(fn ($bulletin) => $bulletin->owner));
    }
}
