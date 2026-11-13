<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Inertia\Middleware;

/** Shares the enum collection ListedEnumResourceController::unnamed() renders, key for key. */
final class MiddlewareWithUnnamedEnumResource extends Middleware
{
    /** Shares request input no enum is named for as the EnumResource collection Laravel builds for it. */
    public function share(Request $request): array
    {
        return ['statuses' => collect($request->array('statuses'))->toResourceCollection(EnumResource::class)];
    }
}
