<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\AbstractKeyedModel;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Workbench\App\Models\Comment;

/** Hands back collections whose declared items are a published model, a framework or abstract one, a word or unread. */
final class ListingShelf
{
    /**
     * Comments it holds.
     *
     * @return EloquentCollection<int, Comment>
     */
    public function comments(): EloquentCollection
    {
        return new EloquentCollection([
            (new Comment)->forceFill(['id' => 3, 'content' => 'C']),
            (new Comment)->forceFill(['id' => 4, 'content' => 'D']),
        ]);
    }

    /**
     * Framework notifications it holds.
     *
     * @return EloquentCollection<int, DatabaseNotification>
     */
    public function notes(): EloquentCollection
    {
        return new EloquentCollection([
            (new DatabaseNotification)->forceFill(['id' => 'n1', 'type' => 'T']),
            (new DatabaseNotification)->forceFill(['id' => 'n2', 'type' => 'T']),
        ]);
    }

    /**
     * Models of an abstract class.
     *
     * @return EloquentCollection<int, AbstractKeyedModel>
     */
    public function abstracts(): EloquentCollection
    {
        return new EloquentCollection([new class extends AbstractKeyedModel {}]);
    }

    /**
     * Words it holds.
     *
     * @return Collection<int, string>
     */
    public function words(): Collection
    {
        return collect(['a', 'b']);
    }

    /**
     * Rows whose type only the body spells.
     */
    public function plain(): Collection
    {
        return collect([['x' => 1], ['x' => 2]]);
    }
}
