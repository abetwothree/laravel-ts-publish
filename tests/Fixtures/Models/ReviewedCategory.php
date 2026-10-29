<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Concerns\HasReviewedParent;
use Workbench\App\Models\Category;

/**
 * A category whose trait redeclares the parent() it inherits, after a reviewer() of its own. PHP 8.4 lists that
 * parent() in the inherited one's place, ahead of reviewer(); PHP 8.5 lists both in the trait's order.
 */
class ReviewedCategory extends Category
{
    use HasReviewedParent;

    protected $table = 'categories';
}
