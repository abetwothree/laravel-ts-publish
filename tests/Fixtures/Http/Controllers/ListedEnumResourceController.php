<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Enums\Status;

/** An Inertia action rendering enum cases through toResourceCollection(EnumResource::class). */
class ListedEnumResourceController
{
    /** Renders every Status case as the EnumResource collection Laravel builds for them. */
    public function index(): Response
    {
        return Inertia::render('ListedEnumResource/Index', [
            'statuses' => collect(Status::cases())->toResourceCollection(EnumResource::class),
        ]);
    }
}
