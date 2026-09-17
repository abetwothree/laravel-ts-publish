<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Appends an accessor its `Attribute<User, never>` docblock types, for a resource that spreads this model's toArray(). */
class BulletinOwnership extends Model
{
    protected $table = 'posts';

    protected $appends = ['owner'];

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<BulletinDigest, $this> */
    public function digest(): BelongsTo
    {
        return $this->belongsTo(BulletinDigest::class, 'user_id');
    }

    /** @return Attribute<User, never> */
    protected function owner(): Attribute
    {
        return Attribute::get(fn () => $this->author);
    }
}
