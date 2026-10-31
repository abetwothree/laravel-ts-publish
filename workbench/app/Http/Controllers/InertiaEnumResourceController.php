<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;

/**
 * Renders an EnumResource prop, which Inertia sends as the resource's objects rather than the bare cases.
 */
class InertiaEnumResourceController
{
    /**
     * Every status case inside the enum resource collection's wrap key.
     *
     * Result should be { statuses: { data: AsEnum<typeof Status>[] } }
     */
    public function index(): Response
    {
        return Inertia::render('Enums/Statuses', [
            'statuses' => EnumResource::collection(Status::cases()),
        ]);
    }

    /**
     * A ternary over two enums' resources, and one of a resource and a bare case of its enum.
     *
     * Result should be { either: AsEnum<typeof Role> | AsEnum<typeof Status>,
     * mixed: AsEnum<typeof Status> | StatusType }
     */
    public function unions(Request $request): Response
    {
        $admin = $request->boolean('admin');

        return Inertia::render('Enums/Unions', [
            'either' => $admin ? EnumResource::make(Role::Admin) : EnumResource::make(Status::Draft),
            'mixed' => $admin ? EnumResource::make(Status::Draft) : Status::Published,
        ]);
    }
}
