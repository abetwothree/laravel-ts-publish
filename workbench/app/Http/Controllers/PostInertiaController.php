<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\Post;

/** Manages blog posts with inertia */
class PostInertiaController
{
    public function index(): Response
    {
        $posts = Post::latest()->paginate(25);

        return Inertia::render('Posts/Index', [
            'posts' => $posts,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Posts/Create');
    }

    public function show(Post $post): Response
    {
        return Inertia::render('Posts/Show', [
            'post' => $post,
        ]);
    }

    public function store(Request $request): Response
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $post = Post::create($validated);

        return Inertia::render('Posts/Show', [
            'post' => $post,
        ]);
    }

    public function edit(Post $post): Response
    {
        return Inertia::render('Posts/Edit', [
            'post' => $post,
        ]);
    }

    public function update(Request $request, Post $post): Response
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $post->update($validated);

        return Inertia::render('Posts/Show', [
            'post' => $post,
        ]);
    }

    public function destroy(Post $post): RedirectResponse
    {
        $post->delete();

        return to_route('posts.index')->with('success', 'Post deleted successfully.');
    }
}
