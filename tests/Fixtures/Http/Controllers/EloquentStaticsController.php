<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\QueriedIdCardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\SearchedPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ShadowingPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services\QueriedIdStats;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/** Renders props read through Eloquent statics, query chains past their builder, and model factory chains. */
class EloquentStaticsController
{
    public function __construct(private QueriedIdStats $stats) {}

    /** Renders reads through a model static and a chain past a query, beside spellings that already read. */
    public function show(): Response
    {
        return Inertia::render('Eloquent/Statics', [
            'findTitle' => Post::find(1)?->title,
            'failTitle' => Post::findOrFail(1)->title,
            'firstId' => Post::query()->pluck('id')->first(),
            'made' => User::factory()->count(2)->make(),
            'queryTitle' => Post::query()->first()?->title,
            'whereFirst' => Post::where('id', 1)->first(),
            'found' => Post::find(1),
        ]);
    }

    /** Renders each step a query chain may take before or after its terminal call. */
    public function steps(): Response
    {
        $newest = fn ($query) => $query->latest();

        return Inertia::render('Eloquent/Steps', [
            'ordered' => Post::orderBy('id')->first()?->title,
            'conditional' => Post::query()->when(true, fn ($query) => $query->where('id', 1))->get(),
            'trashed' => Post::withTrashed()->latest()->first(),
            'titles' => Post::query()->pluck('title'),
            'lastTitle' => Post::query()->latest()->value('title'),
            'comments' => Post::query()->firstOrFail()->comments()->get(),
            'author' => Post::query()->firstOrFail()->author()->first(),
            'rows' => Post::query()->toBase()->get(),
            'ids' => Post::all()->pluck('id')->all(),
            'listed' => Post::find([1]),
            'query' => Post::query()->latest(),
            'proxied' => Post::query()->when(true)->latest()->get(),
            'nullCallback' => Post::query()->when(true, fn ($query) => null)->first(),
            'heldCallback' => Post::query()->when(true, $newest)->get(),
        ]);
    }

    /** Renders a base query's rows through each paginator, past toBase(), getQuery() and a relation. */
    public function basePages(): Response
    {
        return Inertia::render('Eloquent/BasePages', [
            'paged' => Post::query()->toBase()->paginate(),
            'simple' => Post::query()->getQuery()->simplePaginate(),
            'cursor' => Post::query()->toBase()->orderBy('id')->cursorPaginate(),
            'comments' => Post::query()->firstOrFail()->comments()->toBase()->paginate(),
        ]);
    }

    /** Renders terminals on a builder a model's own static returns that Eloquent does not define. */
    public function searched(): Response
    {
        return Inertia::render('Eloquent/Searched', [
            'rows' => SearchedPost::search('x')->get(),
            'filtered' => SearchedPost::search('x')->where('title', 'First')->get(),
            'paged' => SearchedPost::search('x')->paginate(),
            'first' => SearchedPost::search('x')->first(),
        ]);
    }

    /** Renders a model's own statics named like query terminals, and a step past a find() of a variable key. */
    public function ownStatics(): Response
    {
        $ids = [1];

        return Inertia::render('Eloquent/OwnStatics', [
            'cursor' => ShadowingPost::cursor(),
            'value' => ShadowingPost::value('title'),
            'queried' => ShadowingPost::query()->value('title'),
            'firstFound' => Post::find($ids)->first(),
        ]);
    }

    /** Renders a model factory's builds, counted or not, as each count step or argument sets them. */
    public function factories(): Response
    {
        $count = request()->integer('count', 2);

        return Inertia::render('Eloquent/Factories', [
            'one' => User::factory()->state(['name' => 'One'])->make(),
            'counted' => User::factory()->count(2)->create(),
            'timed' => User::factory()->times(2)->make(),
            'argued' => User::factory(2)->make(),
            'cleared' => User::factory()->count(2)->count(null)->make(),
            'single' => User::factory()->count(2)->makeOne(),
            'firstMade' => User::factory()->count(2)->make()->first(),
            'either' => User::factory()->count($count)->make(),
            'eitherFirst' => User::factory($count)->make()->first(),
        ]);
    }

    /** Renders a resolved resource whose body reads a query chain past its builder. */
    public function resolvedCard(Post $post): Response
    {
        return Inertia::render('Eloquent/ResolvedCard', ['card' => QueriedIdCardResource::make($post)->resolve()]);
    }

    /** Renders props a helper builds, reading a query chain past its builder. */
    public function helperMeta(): Response
    {
        return Inertia::render('Eloquent/HelperMeta', ['meta' => $this->meta()]);
    }

    /** Renders props a collaborator builds, reading a query chain past its builder. */
    public function collaboratorStats(): Response
    {
        return Inertia::render('Eloquent/CollaboratorStats', ['stats' => $this->stats->build()]);
    }

    /**
     * The first post's id, read past the query's pluck().
     *
     * @return array<string, mixed>
     */
    private function meta(): array
    {
        return ['firstId' => Post::query()->pluck('id')->first()];
    }
}
