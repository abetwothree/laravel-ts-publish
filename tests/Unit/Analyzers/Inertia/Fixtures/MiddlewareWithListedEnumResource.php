<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Workbench\App\Enums\Status;

/** Shares the enum collection ListedEnumResourceController renders, key for key. */
final class MiddlewareWithListedEnumResource extends Middleware
{
    /** Shares every Status case as the EnumResource collection Laravel builds for them. */
    public function share(Request $request): array
    {
        return ['statuses' => collect(Status::cases())->toResourceCollection(EnumResource::class)];
    }
}
