<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\WarehouseResource;
use Workbench\App\Models\Warehouse;

/** Each resource spelling as shared data, key for key as ControllerWithResourceSpellings::index() renders it. */
final class MiddlewareWithResourceSpellings extends Middleware
{
    public function share(Request $request): array
    {
        return [
            'built' => new WarehouseResource(Warehouse::query()->firstOrFail()),
            'made' => WarehouseResource::make(Warehouse::query()->firstOrFail()),
            'collected' => WarehouseResource::collection(Warehouse::all()),
            'guessed' => Warehouse::query()->firstOrFail()->toResource(),
            'listed' => Warehouse::all()->toResourceCollection(),
            'role' => EnumResource::make(Role::Admin),
            'statuses' => EnumResource::collection(Status::cases()),
        ];
    }
}
