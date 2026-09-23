<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/** A test-only model on the `posts` table whose untyped getters are everyday idioms with an arm the engine cannot type. */
class IdiomPost extends Model
{
    protected $table = 'posts';

    /** Laravel's documented second parameter, read with a null fallback. */
    protected function nickname(): Attribute
    {
        return Attribute::get(fn ($value, array $attributes) => $attributes['title'] ?? null);
    }

    /** A decoded column, or null when it is empty. */
    protected function decodedMeta(): Attribute
    {
        return Attribute::get(fn ($value, $attributes) => $attributes['metadata'] ? json_decode($attributes['metadata'], true) : null);
    }

    /** A decrypted column, or null when it is missing. */
    protected function secretNote(): Attribute
    {
        return Attribute::get(fn ($value, $attributes) => isset($attributes['content']) ? decrypt($attributes['content']) : null);
    }

    /** A key read off an uncast column, with a null fallback. */
    protected function metaTitle(): Attribute
    {
        return Attribute::get(fn () => $this->metadata['title'] ?? null);
    }

    /** A typed column with a null fallback: the arm left is real, so it stays. */
    protected function titleOrNull(): Attribute
    {
        return Attribute::get(fn () => $this->title ?? null);
    }

    /** An old-style getter with no return type. */
    public function getLegacyPayloadAttribute()
    {
        return $this->metadata ? json_decode($this->metadata) : null;
    }
}
