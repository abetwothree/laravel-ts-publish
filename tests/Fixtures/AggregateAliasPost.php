<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Workbench\App\Models\Post;

/**
 * A test-only post that declares seven of its `comments` aggregates itself: five casts, an `@property` tag and an
 * accessor.
 *
 * @property-read float|null $comments_avg_post_id
 */
class AggregateAliasPost extends Post
{
    protected $table = 'posts';

    /**
     * Post's casts, plus one on each of the `withSum('comments', 'post_id')`, `withMax('comments', 'created_at')`,
     * `withMin('comments', 'created_at')`, `withMax('comments', 'updated_at')` and `withAvg('comments', 'id')` aliases.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'comments_sum_post_id' => 'string',
            'comments_max_created_at' => 'datetime',
            'comments_min_created_at' => 'datetime:Y-m-d',
            'comments_max_updated_at' => 'timestamp',
            'comments_avg_id' => 'decimal:2',
        ];
    }

    /**
     * The `withMax('comments', 'post_id')` alias, which turns the SQL NULL over no comments into 0.
     *
     * @return Attribute<int, never>
     */
    protected function commentsMaxPostId(): Attribute
    {
        return Attribute::make(get: fn (?int $value): int => $value ?? 0);
    }
}
