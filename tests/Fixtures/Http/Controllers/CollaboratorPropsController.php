<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services\CardPropsBuilder;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\User;

/** An Inertia action whose whole props a collaborator builds. */
class CollaboratorPropsController
{
    /** Holds the collaborator that builds the props. */
    public function __construct(private readonly CardPropsBuilder $cards) {}

    /** Renders the props the collaborator builds for a user. */
    public function show(User $user): Response
    {
        return Inertia::render('CollaboratorProps/Show', $this->cards->build($user));
    }
}
