<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use Illuminate\Support\Traits\Conditionable;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\User;

/** Renders resources built around the controller's own Conditionable `$this->when()`, which never leaves a prop out. */
class ConditionableWrapController
{
    use Conditionable;

    /** Renders the resource built by make(), by `new` and as a collection. */
    public function show(User $user): Response
    {
        return Inertia::render('Conditionable/Wrap', [
            'made' => UserResource::make($this->when($user->exists, fn () => $user)),
            'built' => new UserResource($this->when($user->exists, fn () => $user)),
            'listed' => UserResource::collection($this->when($user->exists, fn () => [$user])),
        ]);
    }
}
