<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\AlikeWithExtrasResource;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\User;

/** A page prop rendering a resource whose `with()` sends a model and a resource that print one name. */
class AlikeWithExtrasController
{
    public function show(User $user): Response
    {
        return Inertia::render('Extras/Alike', ['item' => new AlikeWithExtrasResource($user)]);
    }
}
