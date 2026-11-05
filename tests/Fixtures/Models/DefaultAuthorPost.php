<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/** A post whose author relation has a default model, which Laravel sends where no row is found. */
class DefaultAuthorPost extends Post
{
    protected $table = 'posts';

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withDefault();
    }
}
