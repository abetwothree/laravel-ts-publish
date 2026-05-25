<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\Post;

class ControllerWithCompact
{
    public function index(): Response
    {
        $posts = Post::latest()->paginate(25);

        return Inertia::render('Posts/Index', compact('posts'));
    }
}
