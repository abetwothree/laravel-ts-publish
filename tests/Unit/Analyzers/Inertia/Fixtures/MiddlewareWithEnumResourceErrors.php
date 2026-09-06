<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Workbench\App\Enums\Role;

/** An EnumResource on the one key collectProps() drops, so its FQCN channel outlives its prop. */
final class MiddlewareWithEnumResourceErrors extends Middleware
{
    public function share(Request $request): array
    {
        return [
            'errors' => EnumResource::make(Role::Admin),
            'ok' => $request->url(),
        ];
    }
}
