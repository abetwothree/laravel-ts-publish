<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ViewerBadgeListResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ViewerBadgeResource;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\Post;

/** Inertia actions whose props hold a resource a self::make() or static::make() factory builds. */
class ViewerBadgeController
{
    /** Renders the signed-in user's badge from each factory. */
    public function page(): Response
    {
        return Inertia::render('ViewerBadge/Page', [
            'selfMake' => ViewerBadgeResource::forViewer(auth()->user()),
            'staticMake' => ViewerBadgeResource::forViewerStatic(auth()->user()),
        ]);
    }

    /** Renders a post's card holding the badge, resolved. */
    public function resolved(Post $post): Response
    {
        return Inertia::render('ViewerBadge/Resolved', ['card' => ViewerBadgeListResource::make($post)->resolve()]);
    }
}
