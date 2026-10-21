<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Workbench\App\Models\Post;

/** A test-only post on the `posts` table that casts `word_count` to a string and adds a `shouted_title` accessor. */
class DraftPost extends Post
{
    protected $table = 'posts';

    /**
     * Post's casts, with `word_count` read as a string.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [...parent::casts(), 'word_count' => 'string'];
    }

    /**
     * The title in capitals.
     *
     * @return Attribute<string, never>
     */
    protected function shoutedTitle(): Attribute
    {
        return Attribute::get(fn (): string => strtoupper((string) $this->title));
    }
}
