<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\PostVisibilitiesResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\Post;

/** Inertia actions rendering enum resources over a route-bound post's nullable enums, alone and beside other arms. */
class NullableEnumResourceController
{
    /** Renders the visibility the post may lack, wrapped. */
    public function show(Post $post): Response
    {
        return Inertia::render('NullableEnumResource/Show', [
            'visibility' => EnumResource::make($post->visibility),
        ]);
    }

    /** Renders a resolved resource whose body wraps that visibility, alone and in a map kept as a Collection. */
    public function resolved(Post $post): Response
    {
        return Inertia::render('NullableEnumResource/Resolved', [
            'card' => PostVisibilitiesResource::make($post)->resolve(),
        ]);
    }

    /** Renders a wrap beside a bare read of its enum, or beside another enum's wrap, as `c` and `d` pick. */
    public function ternaries(Request $request, Post $post): Response
    {
        $c = $request->boolean('c');

        return Inertia::render('NullableEnumResource/Ternaries', [
            'mixed' => $c ? EnumResource::make($post->priority) : $post->priority,
            'nested' => $c ? ($request->boolean('d') ? EnumResource::make($post->priority) : $post->priority) : $post->priority,
            'either' => $c ? EnumResource::make($post->visibility) : EnumResource::make($post->status),
        ]);
    }

    /** Renders one of two enums' wraps, as each branch writes the props. */
    public function branches(Request $request, Post $post): Response
    {
        if ($request->boolean('c')) {
            $props = ['either' => EnumResource::make($post->visibility)];
        } else {
            $props = ['either' => EnumResource::make($post->status)];
        }

        return Inertia::render('NullableEnumResource/Branches', $props);
    }
}
