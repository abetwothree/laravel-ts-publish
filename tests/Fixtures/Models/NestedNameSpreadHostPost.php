<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Models\Post;

/** A test-only post on the `posts` table, with no cast of its own, related to a post whose cast builds on a name. */
class NestedNameSpreadHostPost extends Post
{
    protected $table = 'posts';

    /**
     * A post of the same author whose column cast only builds on a name, which a resource spreads.
     *
     * @return BelongsTo<NestedNameCastPost, $this>
     */
    public function castPost(): BelongsTo
    {
        return $this->belongsTo(NestedNameCastPost::class, 'user_id');
    }
}
