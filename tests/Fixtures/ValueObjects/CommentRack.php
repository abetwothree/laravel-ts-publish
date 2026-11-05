<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;

/** Hands back comments, each under a post that holds comments of its own, listed or keyed by strings. */
final class CommentRack
{
    /**
     * A rack whose keyed lists hold its two comments under the given keys.
     *
     * @param  array{string, string}  $keys
     */
    public function __construct(private readonly array $keys = ['a', 'b']) {}

    /**
     * A comment whose post holds one comment of its own.
     */
    public static function comment(int $id): Comment
    {
        $post = (new Post)->forceFill(['id' => $id * 100, 'title' => 'P'.$id])
            ->setRelation('comments', new EloquentCollection([(new Comment)->forceFill(['id' => $id * 1000, 'content' => 'in'.$id])]));

        return (new Comment)->forceFill(['id' => $id, 'content' => 'c'.$id])->setRelation('post', $post);
    }

    /**
     * A post holding the rack's comments.
     */
    public function post(): Post
    {
        return (new Post)->forceFill(['id' => 1, 'title' => 'T'])
            ->setRelation('comments', new EloquentCollection([self::comment(3), self::comment(4)]));
    }

    /**
     * The comments, listed.
     *
     * @return EloquentCollection<int, Comment>
     */
    public function comments(): EloquentCollection
    {
        return new EloquentCollection([self::comment(3), self::comment(4)]);
    }

    /**
     * The comments, in a collection whose declaration names no item.
     */
    public function bare(): EloquentCollection
    {
        return new EloquentCollection([self::comment(3)]);
    }

    /**
     * The comments under string keys.
     *
     * @return Collection<string, Comment>
     */
    public function keyedComments(): Collection
    {
        return collect(array_combine($this->keys, [self::comment(3), self::comment(4)]));
    }

    /**
     * The comments under string keys, in an Eloquent collection.
     *
     * @return EloquentCollection<string, Comment>
     */
    public function keyedEloquent(): EloquentCollection
    {
        return new EloquentCollection(array_combine($this->keys, [self::comment(3), self::comment(4)]));
    }

    /**
     * The comments under keys of either kind.
     *
     * @return Collection<array-key, Comment>
     */
    public function anyKeyed(): Collection
    {
        return collect(['a' => self::comment(3)]);
    }
}
