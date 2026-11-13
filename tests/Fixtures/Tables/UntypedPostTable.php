<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Tables;

use InertiaUI\Table\Table;
use Workbench\App\Models\Post;

/** A table whose untyped method hands it back with no docblock saying so. */
class UntypedPostTable extends Table
{
    protected ?string $resource = Post::class;

    /**
     * The table, filtered.
     */
    public function filtered()
    {
        return $this;
    }
}
