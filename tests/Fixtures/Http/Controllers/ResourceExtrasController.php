<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ConditionalWithResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HeldResourceWithResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\LooseWithResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\UserCardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\VersionedUserCardCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\WithMetaResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/** Inertia actions whose resource props send keys beside their data, from an `additional()` call or a `with()`. */
class ResourceExtrasController
{
    /** Renders a user's card and every user's card with a meta key added, beside a post whose resource adds its own. */
    public function show(Post $post): Response
    {
        return Inertia::render('ResourceExtras/Show', [
            'one' => UserCardResource::make($post->author)->additional(['meta' => ['n' => 1]]),
            'many' => UserCardResource::collection(User::all())->additional(['meta' => ['n' => 1]]),
            'withed' => WithMetaResource::make($post),
        ]);
    }

    /** Renders a user's card with no key beside it. */
    public function plain(Post $post): Response
    {
        return Inertia::render('ResourceExtras/Plain', ['card' => UserCardResource::make($post->author)]);
    }

    /** Renders a user's card with a meta key added only when the request asks for one. */
    public function maybe(Request $request, Post $post): Response
    {
        return Inertia::render('ResourceExtras/Maybe', [
            'card' => UserCardResource::make($post->author)->additional($request->has('meta') ? ['meta' => 1] : []),
        ]);
    }

    /** Renders cards whose added keys hold a resource and a callable, replace earlier ones, and share `with()`'s. */
    public function encoded(Post $post): Response
    {
        return Inertia::render('ResourceExtras/Encoded', [
            'card' => UserCardResource::make($post->author)
                ->additional(['author' => UserCardResource::make($post->author), 'stamp' => now(...)]),
            'replaced' => UserCardResource::make($post->author)->additional(['a' => 1])->additional(['b' => 'x']),
            'merged' => WithMetaResource::make($post)->additional(['meta' => ['n' => 1]]),
            'clashed' => WithMetaResource::make($post)->additional(['meta' => 2]),
        ]);
    }

    /** Renders a user's card with its post, a model, and that post's status, an enum, added beside it. */
    public function classes(Post $post): Response
    {
        return Inertia::render('ResourceExtras/Classes', [
            'card' => UserCardResource::make($post->author)->additional(['post' => $post, 'status' => $post->status]),
        ]);
    }

    /** Renders collections whose added key is one their `$with` default adds too, and the key their body sits under. */
    public function collisions(): Response
    {
        return Inertia::render('ResourceExtras/Collisions', [
            'versioned' => (new VersionedUserCardCollection(User::all()))->additional(['version' => '2.0']),
            'listed' => UserCardResource::collection(User::all())->additional(['data' => 1]),
        ]);
    }

    /** Renders added keys beside a `with()` no read types, and keys no read types beside a `$with` default. */
    public function loose(Request $request, Post $post): Response
    {
        return Inertia::render('ResourceExtras/Loose', [
            'card' => LooseWithResource::make($post)->additional(['meta' => 2, 'n' => 3]),
            'cards' => (new VersionedUserCardCollection(User::all()))->additional($request->has('v') ? ['version' => '2.0'] : []),
        ]);
    }

    /** Renders a collection whose `$with` default adds a key, and a paginated collection with a meta key added. */
    public function collections(): Response
    {
        return Inertia::render('ResourceExtras/Collections', [
            'versioned' => new VersionedUserCardCollection(User::all()),
            'paged' => UserCardResource::collection(User::query()->paginate())->additional(['meta' => ['n' => 1]]),
        ]);
    }

    /** Renders a post whose `with()` keys the when() family may leave out, alone and beside an `additional()` key. */
    public function conditional(Post $post): Response
    {
        return Inertia::render('ResourceExtras/Conditional', [
            'card' => ConditionalWithResource::make($post),
            'added' => ConditionalWithResource::make($post)->additional(['extra' => 1]),
        ]);
    }

    /** Renders a post whose `with()` holds a resource, its resolved body and a map kept as a Collection. */
    public function held(Post $post): Response
    {
        return Inertia::render('ResourceExtras/Held', ['card' => HeldResourceWithResource::make($post)]);
    }
}
