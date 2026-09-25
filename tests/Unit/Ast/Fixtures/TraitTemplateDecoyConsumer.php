<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Workbench\App\Models\Comment;
use Workbench\App\Models\Concerns\AggregatesChildren;

/**
 * Historically this bound @use AggregatesChildren<SomeOtherModel>
 * but that's stale — see the real tag on the use statement below.
 */
class TraitTemplateDecoyConsumer
{
    /** @use AggregatesChildren<Comment> */
    use AggregatesChildren;
}
