<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Workbench\App\Enums\Role;

/** The same enum reached both wrapped and bare, so its type import has to outlive the AsEnum rewrite. */
final class MiddlewareWithWrappedAndBareEnum extends Middleware
{
    public function share(Request $request): array
    {
        return [
            'role' => EnumResource::make(Role::Admin),
            'bareRole' => $this->currentRole(),
        ];
    }

    protected function currentRole(): Role
    {
        return Role::Admin;
    }
}
