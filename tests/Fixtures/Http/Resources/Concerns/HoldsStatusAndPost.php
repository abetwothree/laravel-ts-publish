<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\Concerns;

use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;

/** Properties whose `@var` names an enum and a model that only this trait's file imports. */
trait HoldsStatusAndPost
{
    /** @var Status */
    public $status;

    /** @var ?Post */
    public $post;
}
