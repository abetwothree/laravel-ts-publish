<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Builders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A custom Eloquent builder another extends, declaring a method that returns `self`.
 *
 * @template TModel of Model
 *
 * @extends Builder<TModel>
 */
class ParentPostBuilder extends Builder
{
    /**
     * Rows with content, declared on the parent as `self`.
     */
    public function withContent(): self
    {
        return $this->whereNotNull('content');
    }
}
