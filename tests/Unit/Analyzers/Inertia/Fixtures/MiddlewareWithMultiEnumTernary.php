<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;

/** A ternary that wraps a different enum in each arm. */
final class MiddlewareWithMultiEnumTernary extends Middleware
{
    public function share(Request $request): array
    {
        return [
            'either' => $request->user() ? EnumResource::make(Role::Admin) : EnumResource::make(Status::Draft),
        ];
    }
}
