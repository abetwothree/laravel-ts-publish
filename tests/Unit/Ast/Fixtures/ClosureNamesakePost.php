<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\Concerns\ProvidesNamesakeMeta;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Models\Post;

/** A post with a vague `: array` helper of its own and one from a trait, and a relation to a namesake-heavy author. */
class ClosureNamesakePost extends Post
{
    use ProvidesNamesakeMeta;

    protected $table = 'posts';

    public function meta(): array
    {
        return ['a' => 1, 'b' => 'x'];
    }

    /** @return BelongsTo<ClosureNamesakeAuthor, $this> */
    public function namesakeAuthor(): BelongsTo
    {
        return $this->belongsTo(ClosureNamesakeAuthor::class, 'user_id');
    }
}
