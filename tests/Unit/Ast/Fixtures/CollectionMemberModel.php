<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Casts\AsEncryptedCollection;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Workbench\App\Models\Comment;
use Workbench\App\Models\User;

/**
 * A model whose members hold a collection: Support\Collection columns cast by `AsCollection`, `AsEncryptedCollection`,
 * `'collection'` and `'encrypted:collection'`, accessors and a method, beside Eloquent\Collection members, some keyed by
 * string, and a to-many relation, whose filters keep models by primary key and return a list.
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
            'visibility' => AsEncryptedCollection::class,
            'content' => 'collection',
            'featured_image_url' => 'encrypted:collection',
        ];
    }

    /** @return Attribute<Collection<string, int>, never> */
    protected function stats(): Attribute
    {
        return Attribute::get(fn () => collect(['a' => 1]));
    }

    /** @return Attribute<Collection<int, User>, never> */
    protected function people(): Attribute
    {
        return Attribute::get(fn () => collect());
    }

    /** @return Attribute<EloquentCollection<int, Comment>, never> */
    protected function kids(): Attribute
    {
        return Attribute::get(fn () => new EloquentCollection);
    }

    /** @return Attribute<EloquentCollection<string, Comment>|null, never> */
    protected function keyedKids(): Attribute
    {
        return Attribute::get(fn () => null);
    }

    /** @return Attribute<EloquentCollection<string, Comment|User>, never> */
    protected function mixedKids(): Attribute
    {
        return Attribute::get(fn () => new EloquentCollection);
    }

    protected function strays(): Attribute
    {
        return Attribute::get(fn (): EloquentCollection => new EloquentCollection);
    }

    /** @return Attribute<Comment, never> */
    protected function lead(): Attribute
    {
        return Attribute::get(fn () => new Comment);
    }

    /** The column's filtered entries beside the key, read as a getter body. */
    protected function optionPicks(): Attribute
    {
        return Attribute::get(fn () => ['options' => $this->options->only(['a', 'b']), 'id' => $this->id]);
    }
}
