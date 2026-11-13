<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi\CardAttributesApiResource;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\Post;

/** An Inertia action whose prop is a JSON:API resource collection resolved to its resource objects. Laravel 12.45+. */
class ResolvedApiCardController
{
    /** Renders every post's resource object. */
    public function index(): Response
    {
        return Inertia::render('ResolvedApiCard/Index', ['cards' => CardAttributesApiResource::collection(Post::all())->resolve()]);
    }
}
