<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\Concerns;

use Workbench\App\Models\Post;

/** Redeclares `$resource` with a `@var` naming the app's `Post`, which only this trait's file imports. */
trait WrapsPost
{
    /** @var Post */
    public $resource;
}
