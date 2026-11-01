<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** A global scope that adds a local macro to each query, as SoftDeletingScope does. */
class HidesArchived implements Scope
{
    /**
     * Keep titled rows only.
     *
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereNotNull('title');
    }

    /**
     * Add `withArchived()`, which lifts this scope and hands the query back.
     *
     * @param  Builder<Model>  $builder
     */
    public function extend(Builder $builder): void
    {
        $builder->macro('withArchived', fn (Builder $builder): Builder => $builder->withoutGlobalScope($this));
    }
}
