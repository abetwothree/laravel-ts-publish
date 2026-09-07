<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\App\Services\PostStatsService;

/**
 * The Inertia shapes real applications write that the rest of the workbench never exercised:
 * Eloquent finders, Inertia v2 prop wrappers, compact(), a ternary-assigned props array,
 * array_merge() props, a request-typed prop, a service call, and a two-branch render.
 */
class InertiaUserShapesController
{
    public function __construct(private readonly PostStatsService $stats) {}

    /**
     * A found model and a nullable found model.
     */
    public function show(int $id): Response
    {
        return Inertia::render('UserShapes/Show', [
            'post' => Post::findOrFail($id),
            'draft' => Post::find($id),
        ]);
    }

    /**
     * Model collections plus a typed request read.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('UserShapes/Index', [
            'users' => User::all(),
            'posts' => Post::query()->latest()->get(),
            'page' => $request->integer('page'),
        ]);
    }

    /**
     * Inertia v2 partial-reload prop wrappers.
     */
    public function deferred(): Response
    {
        return Inertia::render('UserShapes/Deferred', [
            'comments' => Inertia::defer(fn () => Comment::all()),
            'tally' => Inertia::optional(fn () => Post::query()->count()),
        ]);
    }

    /**
     * Props supplied by compact() rather than an array literal.
     */
    public function compacted(int $id): Response
    {
        $post = Post::findOrFail($id);
        $comments = Comment::all();

        return Inertia::render('UserShapes/Compacted', compact('post', 'comments'));
    }

    /**
     * A props array assigned from a ternary, so the two branches disagree on one key.
     */
    public function toggled(Request $request): Response
    {
        $props = $request->boolean('full')
            ? ['post' => Post::find(1), 'views' => 0]
            : ['post' => Post::find(1)];

        return Inertia::render('UserShapes/Toggled', $props);
    }

    /**
     * The authenticated user and a service method with an array-shape docblock.
     */
    public function profile(Request $request): Response
    {
        return Inertia::render('UserShapes/Profile', [
            'user' => $request->user(),
            'stats' => $this->stats->summary(),
        ]);
    }

    /**
     * Props built with array_merge() over a local base array.
     */
    public function merged(): Response
    {
        $base = ['title' => 'Reports'];

        return Inertia::render('UserShapes/Merged', array_merge($base, ['extra' => true]));
    }

    /**
     * Two renders of one component where `detail` exists on only one branch.
     */
    public function branched(Request $request): Response
    {
        if ($request->boolean('detailed')) {
            return Inertia::render('UserShapes/Branched', [
                'post' => Post::find(1),
                'detail' => 'full',
            ]);
        }

        return Inertia::render('UserShapes/Branched', [
            'post' => Post::find(1),
        ]);
    }

    /**
     * A route-bound model parameter.
     */
    public function bound(Post $post): Response
    {
        return Inertia::render('UserShapes/Bound', [
            'post' => $post,
            'stats' => $this->stats->summary(),
        ]);
    }
}
