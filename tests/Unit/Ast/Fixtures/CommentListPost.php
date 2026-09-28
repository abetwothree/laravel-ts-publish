<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Workbench\App\Models\Comment;

/**
 * A post whose accessor counts the times it is invoked, so a test can see how often its waterfall runs.
 *
 * @property-read list<Comment> $comment_list
 */
final class CommentListPost extends Model
{
    /** How many times commentList() has been invoked. */
    public static int $invocations = 0;

    protected $table = 'posts';

    /**
     * The post's comments.
     *
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'post_id');
    }

    /** A to-many filter: vague without imports, while the tag names a class a reader without imports cannot import. */
    protected function commentList(): Attribute
    {
        self::$invocations++;

        return Attribute::get(fn () => $this->comments->only([1, 2]));
    }
}
