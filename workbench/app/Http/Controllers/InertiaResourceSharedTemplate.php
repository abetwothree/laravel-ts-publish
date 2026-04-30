<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Http\Resources\WarehouseResource;
use Workbench\App\Models\Warehouse;

/**
 * The purpose is to make sure the return types are properly grouped and defined for the same template that is used across different methods.
 *
 * Result should be:
 * { warehouses: JsonResourcePaginator<WarehouseResource>, warehouse_get: AnonymousResourceCollection<WarehouseResource>, warehouse_all: AnonymousResourceCollection<WarehouseResource>, warehouse_first: WarehouseResource, warehouse_find: WarehouseResource }
 */
class InertiaResourceSharedTemplate
{
    public function resourcePaginatedCollection(): Response
    {
        $warehouses = Warehouse::latest()->paginate(25);

        return Inertia::render('Resource/SharedTemplate', [
            'warehouses' => WarehouseResource::collection($warehouses),
        ]);
    }

    public function resourceAnonymousCollection(): Response
    {
        $warehouseGet = Warehouse::latest()->limit(25)->get();
        $warehouseAll = Warehouse::latest()->all();

        return Inertia::render('Resource/SharedTemplate', [
            'warehouse_get' => WarehouseResource::collection($warehouseGet),
            'warehouse_all' => WarehouseResource::collection($warehouseAll),
        ]);
    }

    public function resource(): Response
    {
        $warehouseFirst = Warehouse::latest()->first();
        $warehouseFind = Warehouse::find(1);

        return Inertia::render('Resource/SharedTemplate', [
            'warehouse_first' => new WarehouseResource($warehouseFirst),
            'warehouse_find' => WarehouseResource::make($warehouseFind),
        ]);
    }
}
