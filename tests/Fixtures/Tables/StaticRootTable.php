<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Tables;

use InertiaUI\Table\Table;
use Workbench\App\Models\Post;

/** A table with static roots other than make(): one returning the table, one a number. */
class StaticRootTable extends Table
{
    protected ?string $resource = Post::class;

    /** Build a table, as make() does. */
    public static function named(): static
    {
        return new static;
    }

    /** A number that is no table. */
    public static function total(): int
    {
        return 4;
    }
}
