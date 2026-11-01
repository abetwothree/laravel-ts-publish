<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services\PostPages;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\Post;

/** Renders resource collections over a paginator a builder variable, a helper or a service holds, and its own steps. */
class PaginatorSourcesController
{
    public function __construct(private PostPages $pages) {}

    /** Renders a collection over a length-aware paginator from each source. */
    public function index(): Response
    {
        $query = Post::query()->where('id', '>', 0);

        return Inertia::render('Paginators/Sources', [
            'fromVariable' => PostResource::collection($query->paginate()),
            'fromHelper' => PostResource::collection($this->page()),
            'fromService' => PostResource::collection($this->pages->page()),
        ]);
    }

    /** Renders a collection over the simple and cursor paginators, and over a paginator's own steps. */
    public function kinds(): Response
    {
        $query = Post::query()->where('id', '>', 0);

        return Inertia::render('Paginators/Kinds', [
            'simpleVariable' => PostResource::collection($query->simplePaginate()),
            'cursorVariable' => PostResource::collection($query->cursorPaginate()),
            'simpleService' => PostResource::collection($this->pages->simplePage()),
            'cursorService' => PostResource::collection($this->pages->cursorPage()),
            'steppedVariable' => PostResource::collection($query->paginate()->withQueryString()),
            'steppedService' => PostResource::collection($this->pages->page()->onEachSide(1)),
        ]);
    }

    /** Renders a paginator past its own steps, each handing back the same paginator. */
    public function steps(): Response
    {
        return Inertia::render('Paginators/Steps', [
            'withQueryString' => Post::query()->paginate(10)->withQueryString(),
            'onEachSide' => Post::query()->paginate(10)->onEachSide(1),
            'fragment' => Post::query()->simplePaginate()->appends('sort', 'title')->fragment('posts'),
            'base' => Post::query()->toBase()->orderBy('id')->cursorPaginate()->withPath('/posts'),
            'titles' => Post::query()->paginate()->through(fn (Post $post) => $post->title),
            'unread' => Post::query()->paginate()->through($this->titleOf(...)),
        ]);
    }

    /**
     * A length-aware page of posts.
     */
    private function page(): LengthAwarePaginator
    {
        return Post::query()->paginate();
    }

    /**
     * A post's title.
     */
    private function titleOf(Post $post): string
    {
        return $post->title;
    }
}
