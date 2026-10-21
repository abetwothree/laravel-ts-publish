<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Concerns\HasTreeAncestors;
use Workbench\App\Models\Category;

/** A category whose ancestors() a trait declares without a native return type. */
class TreeCategory extends Category
{
    use HasTreeAncestors;

    protected $table = 'categories';
}
