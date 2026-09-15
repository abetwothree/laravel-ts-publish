<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Models\Comment;

/** A test-only model on the `posts` table whose casts and accessors pin resolveAttributeClass() edge cases. */
class ReceiverAttributeBaseModel extends Model
{
    protected $table = 'posts';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'immutable_datetime',
            'deleted_at' => 'timestamp',
            'content' => ReceiverDualCast::class,
        ];
    }

    /**
     * A builtin getter type that the docblock contradicts; the getter wins.
     *
     * @return Attribute<Comment, never>
     */
    protected function typedLabel(): Attribute
    {
        return Attribute::get(fn (): string => 'label');
    }

    /** A getter returning `self`, which names this class even when read through a subclass. */
    protected function selfCopy(): Attribute
    {
        return Attribute::get(fn (): self => $this);
    }

    /** A getter returning `static`, which names the class it is read through. */
    protected function staticCopy(): Attribute
    {
        return Attribute::get(fn (): static => $this);
    }
}
