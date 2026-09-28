<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\Concerns\GenericChildrenTrait;
use Workbench\App\Models\Comment;

/**
 * Historically this bound @use GenericChildrenTrait<SomeOtherModel>
 * but that's stale — see the real tag on the use statement below.
 */
class GenericChildrenDecoyConsumer
{
    /** @use GenericChildrenTrait<Comment> */
    use GenericChildrenTrait;
}
