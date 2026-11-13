<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

/** A test-only post whose `$appends` writes its `priority_wrap` accessor, a resource around its nullable priority. */
class AppendedPriorityWrapPost extends WrappedPriorityPost
{
    protected $appends = ['priority_wrap'];
}
