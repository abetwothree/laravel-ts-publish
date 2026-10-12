<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services\PostSummaryFormatter;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\Post;

class InertiaFormatterController
{
    /**
     * Read the post through an injected formatter.
     */
    public function __construct(private readonly PostSummaryFormatter $formatter) {}

    /**
     * A prop built by a service helper whose parameter declares no type.
     */
    public function show(Post $post): Response
    {
        return Inertia::render('Posts/Show', ['row' => $this->formatter->row($post)]);
    }
}
