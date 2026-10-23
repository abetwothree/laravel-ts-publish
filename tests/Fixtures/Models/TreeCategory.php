<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Concerns\HasTreeAncestors;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Relations\FirstRelated;
use Workbench\App\Models\Category;

/**
 * A category whose ancestors() a trait declares without a native return type, whose branches() declares no return at
 * all, so no relation list reads it, and whose firstBranch() loads one model through a subclass of HasMany.
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
}
