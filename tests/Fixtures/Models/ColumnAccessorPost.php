<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Models\User;
use Workbench\App\Support\Opaque;

/**
 * A test-only model on the `posts` table whose untyped getters return its nullable `featured_image_url` column, its
 * author or a literal `null`, and a map over a value no rule types.
 */
class ColumnAccessorPost extends Model
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

    /** The featured image's URL, read through a getter that declares nothing. */
    protected function image(): Attribute
    {
        return Attribute::get(fn () => $this->featured_image_url);
    }

    /** The author once the post is published, else a literal `null`, read through a getter that declares nothing. */
    protected function publishedAuthor(): Attribute
    {
        return Attribute::get(fn () => $this->published_at !== null ? $this->author : null);
    }

    /** Each tag of a payload no rule types, as a string, read through a getter that declares nothing. */
    protected function tagNames(): Attribute
    {
        return Attribute::get(fn () => array_map(fn ($tag): string => (string) $tag, Opaque::value([])));
    }
}
