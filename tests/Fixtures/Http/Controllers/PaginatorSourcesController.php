<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services\PostPages;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Collection;
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

    /** Renders collections over helpers whose declared class decides a paginator kind or leaves it open. */
    public function declared(): Response
    {
        return Inertia::render('Paginators/Declared', [
            'contract' => PostResource::collection($this->anyPage()),
            'abstract' => PostResource::collection($this->abstractPage()),
            'either' => PostResource::collection($this->pageOrAll()),
            'fragment' => Post::query()->paginate()->fragment(),
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
     * A page of posts as the contract either paginator kind implements, from a call the engine cannot read.
     */
    private function anyPage(): Paginator
    {
        return call_user_func([$this->pages, 'simplePage']);
    }

    /**
     * A page of posts as the base class either paginator kind extends, from a call the engine cannot read.
     */
    private function abstractPage(): AbstractPaginator
    {
        return call_user_func([Post::query(), 'paginate']);
    }

    /**
     * A page of posts, declared as either a page or a collection.
     *
     * @return LengthAwarePaginator<int, Post>|Collection<int, Post>
     */
    private function pageOrAll(): LengthAwarePaginator|Collection
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
