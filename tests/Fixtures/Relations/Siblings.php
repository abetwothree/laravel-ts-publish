<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Relations;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A to-many relation that replaces getResults() and match() and still loads a collection, as an adjacency-list
 * package's siblings relation does.
 *
 * @template TRelatedModel of Model
 * @template TDeclaringModel of Model
 *
 * @extends HasMany<TRelatedModel, TDeclaringModel>
 */
class Siblings extends HasMany
{
    /**
     * The related models.
     *
     * @return EloquentCollection<int, TRelatedModel>
     */
    public function getResults()
    {
        return $this->query->get();
    }

    /**
     * Sets each model's related models as a collection, as HasMany does.
     *
     * @param  array<int, TDeclaringModel>  $models
     * @param  EloquentCollection<int, TRelatedModel>  $results
     * @return array<int, TDeclaringModel>
     */
    public function match(array $models, EloquentCollection $results, $relation)
    {
        return $this->matchMany($models, $results, $relation);
    }
}
