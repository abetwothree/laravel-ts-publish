<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Reads Bulletin's accessors through a typed closure parameter and a nullsafe relation chain; each names its own class,
 * so the file imports `Comment` and `User` only if both reads carry the accessor's class.
 */
class BulletinBoard extends Model
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

    /** The same reads in a method body, which carries no import and so publishes them without a class. */
    public function summary(): array
    {
        return [
            'comment_lists' => $this->bulletins->map(fn (Bulletin $bulletin) => $bulletin->comment_list),
            'lead_author' => $this->lead?->author_pick,
            'id' => $this->id,
        ];
    }

    protected function commentLists(): Attribute
    {
        return Attribute::get(fn () => $this->bulletins->map(fn (Bulletin $bulletin) => $bulletin->comment_list));
    }

    protected function leadAuthor(): Attribute
    {
        return Attribute::get(fn () => $this->lead?->author_pick);
    }
}
