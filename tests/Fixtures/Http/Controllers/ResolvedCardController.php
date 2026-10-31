<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\CastCardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\CheckedResourceElvisResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HelperViewerCardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\PostCardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\UserCardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ViewerCardResource;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Http\Resources\PreserveKeysPropertyCollection;
use Workbench\App\Models\Post;
use Workbench\App\Models\Team;
use Workbench\App\Models\User;

/** Inertia actions whose props are API resources resolved to the keys their bodies return. */
class ResolvedCardController
{
    /** Renders a post's card, built by make(). */
    public function show(Post $post): Response
    {
        return Inertia::render('ResolvedCard/Show', ['card' => PostCardResource::make($post)->resolve()]);
    }

    /** Renders a post's card, built by new. */
    public function built(Post $post): Response
    {
        return Inertia::render('ResolvedCard/Built', ['card' => new PostCardResource($post)->resolve()]);
    }

    /** Renders a user's card, whose body holds no API resource. */
    public function plain(User $user): Response
    {
        return Inertia::render('ResolvedCard/Plain', ['card' => UserCardResource::make($user)->resolve()]);
    }

    /** Renders a post's card beside the signed-in user's, null for a guest. */
    public function viewer(Post $post): Response
    {
        return Inertia::render('ResolvedCard/Viewer', ['card' => ViewerCardResource::make($post)->resolve()]);
    }

    /** Renders a post's card beside the signed-in user's a service builds, null for a guest. */
    public function helperViewer(Post $post): Response
    {
        return Inertia::render('ResolvedCard/HelperViewer', ['card' => HelperViewerCardResource::make($post)->resolve()]);
    }

    /** Renders a post's card spread into the props, beside the signed-in user's, null for a guest. */
    public function viewerSpread(Post $post): Response
    {
        return Inertia::render('ResolvedCard/ViewerSpread', [...ViewerCardResource::make($post)->resolve()]);
    }

    /** Renders every post's card. */
    public function index(): Response
    {
        return Inertia::render('ResolvedCard/Index', ['cards' => PostCardResource::collection(Post::all())->resolve()]);
    }

    /** Renders a post's card, two of whose author cards its resource's casts retype. */
    public function cast(Post $post): Response
    {
        return Inertia::render('ResolvedCard/Cast', ['card' => CastCardResource::make($post)->resolve()]);
    }

    /** Renders a resource whose short ternaries filter() may send as null. */
    public function elvis(): Response
    {
        return Inertia::render('ResolvedCard/Elvis', ['card' => CheckedResourceElvisResource::make(null)->resolve()]);
    }

    /** Renders every team's card under its slug, from a collection that keeps its source keys. */
    public function teams(): Response
    {
        return Inertia::render('ResolvedCard/Teams', [
            'teams' => PreserveKeysPropertyCollection::make(Team::with('owner')->get()->keyBy('slug'))->resolve(),
        ]);
    }
}
