<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Models\User;

/** A test-only model on the `posts` table whose old-style accessor shares its key with a relation. */
class ShadowedAccessorPost extends Model
{
    protected $table = 'posts';

    /**
     * The post's author.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** The author's display name, under the key the relation also takes. */
    public function getAuthorAttribute(): string
    {
        return 'Ada';
    }
}
