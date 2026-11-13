<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Concerns;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Builders\SearchBuilder;

/** A stand-in for Laravel Scout's `Searchable`, whose `search()` returns a builder Eloquent does not define. */
trait SearchesLikeScout
{
    /**
     * A search of the model's records.
     *
     * @return SearchBuilder<static>
     */
    public static function search(string $query = ''): SearchBuilder
    {
        return new SearchBuilder(new static, $query);
    }
}
