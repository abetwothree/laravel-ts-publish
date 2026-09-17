<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Reads Bulletin's accessors through pluck() and inside a shape a closure parameter builds. */
class BulletinArchive extends Model
{
    protected $table = 'posts';

    /** @return HasMany<Bulletin, $this> */
    public function bulletins(): HasMany
    {
        return $this->hasMany(Bulletin::class, 'user_id');
    }

    protected function commentLists(): Attribute
    {
        return Attribute::get(fn () => $this->bulletins->pluck('comment_list'));
    }

    protected function authorRows(): Attribute
    {
        return Attribute::get(fn () => $this->bulletins->map(fn (Bulletin $bulletin) => ['author' => $bulletin->author_pick]));
    }
}
