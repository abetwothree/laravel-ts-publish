<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Tables;

use InertiaUI\Table\Table;
use Workbench\App\Models\Post;

/** A table whose methods may hand back no table, documented spaced or not and typed `?static`, and one that may not. */
class NullableStaticTable extends Table
{
    protected ?string $resource = Post::class;

    /** @return static | null */
    public function orNothing()
    {
        return random_int(0, 1) === 1 ? $this : null;
    }

    /** @return static|null */
    public function orNull()
    {
        return random_int(0, 1) === 1 ? $this : null;
    }

    /** The table or null, typed natively. */
    public function orNone(): ?static
    {
        return random_int(0, 1) === 1 ? $this : null;
    }

    /** @return static */
    public function always()
    {
        return $this;
    }
}
