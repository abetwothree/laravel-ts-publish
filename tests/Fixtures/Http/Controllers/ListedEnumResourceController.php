<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Enums\Status;

/** Inertia actions rendering enum lists through toResourceCollection(EnumResource::class). */
class ListedEnumResourceController
{
    /** Renders every Status case as the EnumResource collection Laravel builds for them. */
    public function index(): Response
    {
        return Inertia::render('ListedEnumResource/Index', [
            'statuses' => collect(Status::cases())->toResourceCollection(EnumResource::class),
        ]);
    }

    /** Renders request input no enum is named for as the EnumResource collection Laravel builds for it. */
    public function unnamed(Request $request): Response
    {
        return Inertia::render('ListedEnumResource/Unnamed', [
            'statuses' => collect($request->array('statuses'))->toResourceCollection(EnumResource::class),
        ]);
    }
}
