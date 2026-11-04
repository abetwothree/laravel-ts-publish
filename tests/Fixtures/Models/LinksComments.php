<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

/**
 * A templated trait kept in `LinkingPost`'s namespace, not in `Concerns/`, so its user names it with no import. Its
 * bodies type nothing, so only the user's `@use` tag types its accessors.
 *
 * @template TModel of Model
 */
trait LinksComments
{
    /**
     * Read the latest linked model.
     *
     * @return TModel|null
     */
    public function getLatestAttribute(): mixed
    {
        return $this->getRelationValue('latest');
    }

    /**
     * Read the linked models.
     *
     * @return Attribute<EloquentCollection<int, TModel>, never>
     */
    protected function linkedItems(): Attribute
    {
        return Attribute::get(fn () => $this->getRelationValue('linked'));
    }

    /**
     * Read the first linked model.
     *
     * @return Attribute<TModel|null, never>
     */
    protected function firstLinked(): Attribute
    {
        return Attribute::get(fn () => $this->getRelationValue('first'));
    }
}
