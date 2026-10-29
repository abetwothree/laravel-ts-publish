<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Concerns\HasTreeAncestors;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Relations\Descendants;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Relations\FirstRelated;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Relations\Siblings;
use Workbench\App\Models\Category;

/**
 * A category whose ancestors() a trait declares without a native return type, whose branches() declares no return at
 * all, so no relation list reads it, whose firstBranch() loads one model through a subclass of HasMany, and whose
 * descendants() and siblings() load a list through subclasses that replace how HasMany loads it.
 */
class TreeCategory extends Category
{
    use HasTreeAncestors;

    protected $table = 'categories';

    /**
     * The categories directly under this one, built through the trait's helper with no declared return.
     */
    public function branches()
    {
        return $this->newAncestors($this->newQuery(), $this, 'parent_id', 'id');
    }

    /**
     * The first category directly under this one, or null.
     *
     * @return FirstRelated<static, $this>
     */
    public function firstBranch(): FirstRelated
    {
        return new FirstRelated($this->newQuery(), $this, 'parent_id', 'id');
    }

    /**
     * The categories directly under this one, through a relation that replaces match().
     *
     * @return Descendants<static, $this>
     */
    public function descendants(): Descendants
    {
        return new Descendants($this->newQuery(), $this, 'parent_id', 'id');
    }

    /**
     * The categories directly under this one, through a relation that replaces getResults() and match().
     *
     * @return Siblings<static, $this>
     */
    public function siblings(): Siblings
    {
        return new Siblings($this->newQuery(), $this, 'parent_id', 'id');
    }
}
