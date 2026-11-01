<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Workbench\App\Models\Post;

/** Hands a controller each kind of paginator over the posts, as an injected service. */
class PostPages
{
    /**
     * A length-aware page of posts.
     */
    public function page(): LengthAwarePaginator
    {
        return Post::query()->paginate();
    }

    /**
     * A simple page of posts.
     */
    public function simplePage(): Paginator
    {
        return Post::query()->simplePaginate();
    }

    /**
     * A cursor page of posts.
     */
    public function cursorPage(): CursorPaginator
    {
        return Post::query()->cursorPaginate();
    }
}
