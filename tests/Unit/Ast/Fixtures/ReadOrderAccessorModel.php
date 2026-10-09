<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Workbench\App\Models\Comment;

/**
 * An accessor whose getter reads an untyped method that reads the accessor back, and whose import-less spelling is
 * vague, so the method's shape depends on the import-less tie-break the accessor's own analysis is running.
 */
final class ReadOrderAccessorModel extends Model
{
    protected $table = 'posts';

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'post_id');
    }

    /** Untyped, so the body fallback reads the shape it returns. */
    public function listing()
    {
        return ['v' => $this->listed_comments, 'id' => $this->id];
    }

    /** Never null on the left, so the right operand only widens what the engine reads. */
    protected function listedComments(): Attribute
    {
        return Attribute::get(fn () => $this->comments->only([1, 2]) ?? collect($this->listing())->all());
    }
}
