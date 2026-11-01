<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Tables;

use InertiaUI\Table\Table as UiTable;
use Workbench\App\Models\Post;

/** A table whose untyped methods say what they return only in their docblocks, by a short name and an alias. */
class DocblockPostTable extends UiTable
{
    protected ?string $resource = Post::class;

    /** @return DocblockPostTable */
    public function fluentShort()
    {
        return $this;
    }

    /** @return UiTable */
    public function fluentAliased()
    {
        return $this;
    }

    /** @return int */
    public function total()
    {
        return 3;
    }
}
