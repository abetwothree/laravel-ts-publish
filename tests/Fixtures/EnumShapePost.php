<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Comment;

/** A test-only model on the `posts` table whose untyped getters build a shape, a list or a union around its enum. */
class EnumShapePost extends Model
{
    protected $table = 'posts';

    /**
     * The status column's enum.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => Status::class];
    }

    /**
     * The post's comments.
     *
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'post_id');
    }

    /** A shape holding the enum. */
    protected function badge(): Attribute
    {
        return Attribute::get(fn () => ['status' => $this->status, 'label' => 'post']);
    }

    /** A list of shapes holding the enum. */
    protected function stateList(): Attribute
    {
        return Attribute::get(fn () => $this->comments->map(fn (Comment $comment) => ['id' => $comment->id, 'state' => $this->status])->all());
    }

    /** The enum or a string. */
    protected function stateOrNone(): Attribute
    {
        return Attribute::get(fn () => $this->title !== '' ? $this->status : 'none');
    }

    /** The enum alone, which the model's Resource interface prints as its AsEnum wrap. */
    protected function plainState(): Attribute
    {
        return Attribute::get(fn () => $this->status);
    }
}
