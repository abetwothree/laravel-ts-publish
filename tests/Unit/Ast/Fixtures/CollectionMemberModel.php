<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Workbench\App\Models\Comment;

/**
 * A model whose members hold a Support\Collection: a collection-cast column, an accessor and a method, beside a
 * column cast to an Eloquent collection and a to-many relation, whose filters keep models by primary key.
 */
final class CollectionMemberModel extends Model
{
    protected $table = 'posts';

    /** @return Collection<string, int> */
    public function tally(): Collection
    {
        return collect(['a' => 1]);
    }

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'post_id');
    }

    /**
     * The column's filtered entries beside the key, read as a method body.
     *
     * @return array<string, mixed>
     */
    public function optionFields(): array
    {
        return ['options' => $this->options->only(['a', 'b']), 'id' => $this->id];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'options' => AsCollection::class,
            'metadata' => AsCollection::using(EloquentCollection::class),
        ];
    }

    /** @return Attribute<Collection<string, int>, never> */
    protected function stats(): Attribute
    {
        return Attribute::get(fn () => collect(['a' => 1]));
    }

    /** The column's filtered entries beside the key, read as a getter body. */
    protected function optionPicks(): Attribute
    {
        return Attribute::get(fn () => ['options' => $this->options->only(['a', 'b']), 'id' => $this->id]);
    }
}
