<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Relations;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A to-one relation built on HasMany, as a package's has-one-deep relation is built on has-many-through: loaded
 * lazily or eagerly, it holds the first related model, or null.
 *
 * @template TRelatedModel of Model
 * @template TDeclaringModel of Model
 *
 * @extends HasMany<TRelatedModel, TDeclaringModel>
 */
class FirstRelated extends HasMany
{
    /**
     * The first related model, or null.
     *
     * @return TRelatedModel|null
     */
    public function getResults()
    {
        return $this->first();
    }

    /**
     * Sets the relation on each model to null, which match() replaces where a related model exists.
     *
     * @param  array<int, TDeclaringModel>  $models
     * @return array<int, TDeclaringModel>
     */
    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, null);
        }

        return $models;
    }

    /**
     * Sets each model's first related model, as a has-one relation does.
     *
     * @param  array<int, TDeclaringModel>  $models
     * @param  EloquentCollection<int, TRelatedModel>  $results
     * @return array<int, TDeclaringModel>
     */
    public function match(array $models, EloquentCollection $results, $relation)
    {
        return $this->matchOne($models, $results, $relation);
    }
}
