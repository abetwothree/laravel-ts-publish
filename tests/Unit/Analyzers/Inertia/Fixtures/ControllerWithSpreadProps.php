<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\User;

/**
 * Regression (Task 32 review, fix round 2): the render call's props array IS the whole return
 * shape (topLevel: true), so a top-level resource spread inside it must flatten, not vanish.
 */
class ControllerWithSpreadProps
{
    public function index(User $user): Response
    {
        return Inertia::render('Dashboard/Spread', [
            ...UserResource::make($user)->resolve(),
            'flag' => true,
        ]);
    }
}
