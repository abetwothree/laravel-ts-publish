<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Builders;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * A search builder no Eloquent class defines, with Laravel Scout's `Builder` signatures, over a model's own query.
 *
 * @template TModel of Model
 */
class SearchBuilder
{
    /**
     * @param  TModel  $model
     */
    public function __construct(public Model $model, public string $query = '') {}

    /**
     * Filter on a field, handing the builder back.
     *
     * @return $this
     */
    public function where(string $field, mixed $value)
    {
        return $this;
    }

    /**
     * The first match.
     *
     * @return TModel|null
     */
    public function first()
    {
        return $this->model->newQuery()->first();
    }

    /**
     * Every match.
     *
     * @return Collection<int, TModel>
     */
    public function get()
    {
        return $this->model->newQuery()->get();
    }

    /**
     * A page of matches.
     */
    public function paginate(?int $perPage = null): LengthAwarePaginator
    {
        return $this->model->newQuery()->paginate($perPage);
    }
}
