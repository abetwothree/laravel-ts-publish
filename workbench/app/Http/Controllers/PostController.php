<?php

namespace Workbench\App\Http\Controllers;

use Illuminate\Http\Request;
use Workbench\App\Models\Post;

/** Manages blog posts */
class PostController
{
    public function index(): void {}

    public function show(Post $post): void {}

    public function store(Request $request): void {}

    public function update(Request $request, Post $post): void {}

    public function destroy(Post $post): void {}
}
