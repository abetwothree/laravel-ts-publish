<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;

/** Shared props that are a ternary of two enums' resources, and of a resource and a bare case of its enum. */
final class MiddlewareWithEnumUnions extends Middleware
{
    /** Shares each ternary, whose arms the `c` query flag picks. */
    public function share(Request $request): array
    {
        $c = $request->boolean('c');

        return [
            'either' => $c ? EnumResource::make(Role::Admin) : EnumResource::make(Status::Draft),
            'mixed' => $c ? EnumResource::make(Status::Draft) : Status::Published,
        ];
    }
}
