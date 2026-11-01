<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Builders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A custom Eloquent builder with a query method of its own, which hands the builder back.
 *
 * @template TModel of Model
 *
 * @extends Builder<TModel>
 */
class PublishedPostBuilder extends Builder
{
    /**
     * Rows with a title.
     */
    public function titled(): static
    {
        return $this->whereNotNull('title');
    }
}
