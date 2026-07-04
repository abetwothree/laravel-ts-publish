<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Workbench\App\Http\Requests\StorePostRequest;
use Workbench\App\Http\Requests\UpdatePostRequest;
use Workbench\App\Models\Post;

/** Manages blog posts */
class PostController
{
    public function index(): void {}

    public function show(Post $post): void {}

    public function store(StorePostRequest $request): void {}

    public function update(UpdatePostRequest $request, Post $post): void {}

    public function destroy(Post $post): void {}
}
