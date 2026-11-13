<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Builders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A custom Eloquent builder with query methods of its own, each handing the builder back.
 *
 * @template TModel of Model
 *
 * @extends ParentPostBuilder<TModel>
 */
class PublishedPostBuilder extends ParentPostBuilder
{
    /**
     * Rows with a title.
     */
    public function titled(): static
    {
        return $this->whereNotNull('title');
    }

    /**
     * Rows with a title, declared as the base builder.
     */
    public function published(): Builder
    {
        return $this->whereNotNull('title');
    }
}
