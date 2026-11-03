<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Tables;

use InertiaUI\Table\Table;
use Workbench\App\Models\Post;

/** A table whose methods may hand back no table: one documented `@return static | null`, one typed `?static`. */
class NullableStaticTable extends Table
{
    protected ?string $resource = Post::class;

    /** @return static | null */
    public function orNothing()
    {
        return random_int(0, 1) === 1 ? $this : null;
    }

    /** The table or null, typed natively. */
    public function orNone(): ?static
    {
        return random_int(0, 1) === 1 ? $this : null;
    }
}
