<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Concerns\HasTreeAncestors;
use Workbench\App\Models\Category;

/**
 * A category whose ancestors() a trait declares without a native return type, and whose branches() declares no return
 * at all, so only ancestors() is read as a relation.
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
}
