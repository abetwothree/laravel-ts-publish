<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;

/** Shared props reached only through EnumResource::make(), top level and nested in an inline array. */
final class MiddlewareWithEnumResource extends Middleware
{
    public function share(Request $request): array
    {
        return [
            'role' => EnumResource::make(Role::Admin),
            'status' => EnumResource::make(Status::Draft),
            'nested' => ['role' => EnumResource::make(Role::Admin)],
        ];
    }
}
