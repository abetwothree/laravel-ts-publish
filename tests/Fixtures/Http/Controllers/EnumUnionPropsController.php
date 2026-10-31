<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;

/** A test-only controller whose props are a ternary of two enums' resources, and of a resource and a bare case. */
class EnumUnionPropsController
{
    /** Renders each ternary, whose arms the `c` query flag picks. */
    public function show(Request $request): Response
    {
        $c = $request->boolean('c');

        return Inertia::render('EnumUnions/Show', [
            'either' => $c ? EnumResource::make(Role::Admin) : EnumResource::make(Status::Draft),
            'mixed' => $c ? EnumResource::make(Status::Draft) : Status::Published,
        ]);
    }

    /** Renders the same ternaries over enum resource collections, which Inertia sends inside their wrap key. */
    public function collections(Request $request): Response
    {
        $c = $request->boolean('c');

        return Inertia::render('EnumUnions/Collections', [
            'either' => $c ? EnumResource::collection(Role::cases()) : EnumResource::collection(Status::cases()),
            'mixed' => $c ? EnumResource::collection(Status::cases()) : Status::Published,
        ]);
    }
}
