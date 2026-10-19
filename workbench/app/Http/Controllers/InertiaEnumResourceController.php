<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Inertia\Inertia;
use Inertia\Response;
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
}
