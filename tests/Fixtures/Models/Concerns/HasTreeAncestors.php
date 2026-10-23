<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Concerns;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Relations\Ancestors;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Declares ancestors() with no native return type, built through its own helper. */
trait HasTreeAncestors
{
    /**
     * The model's ancestors.
     *
     * @return Ancestors<static, static>
     */
    public function ancestors()
    {
        return $this->newAncestors($this->newQuery(), $this, 'parent_id', 'id');
    }

    /**
     * Build the ancestors relation.
     *
     * @param  Builder<static>  $query
     * @return Ancestors<static, static>
     */
    protected function newAncestors(Builder $query, Model $parent, string $foreignKey, string $localKey): Ancestors
    {
        return new Ancestors($query, $parent, $foreignKey, $localKey);
    }
}
