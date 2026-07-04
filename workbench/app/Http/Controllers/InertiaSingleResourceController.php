<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Http\Resources\WarehouseResource;
use Workbench\App\Models\Warehouse;

class InertiaSingleResourceController
{
    /**
     * An anonymous with paginated collection of results
     *
     * Test page type is { warehouses: JsonResourcePaginator<WarehouseResource> }
     */
    public function resourcePaginatedCollection(): Response
    {
        $warehouses = Warehouse::latest()->paginate(25);

        return Inertia::render('Resource/PaginatedWarehouse', [
            'warehouses' => WarehouseResource::collection($warehouses),
        ]);
    }

    /**
     * An anonymous with a collection of results
     *
     * Test page type is { warehouse_get: AnonymousResourceCollection<WarehouseResource>, warehouse_all: AnonymousResourceCollection<WarehouseResource> }
     */
    public function resourceAnonymousCollection(): Response
    {
        $warehouseGet = Warehouse::latest()->limit(25)->get();
        $warehouseAll = Warehouse::latest()->all();

        return Inertia::render('Resource/AnonymousWarehouse', [
            'warehouse_get' => WarehouseResource::collection($warehouseGet),
            'warehouse_all' => WarehouseResource::collection($warehouseAll),
        ]);
    }

    /**
     * Test single resource returns
     *
     * Return type is { warehouse_first: WarehouseResource, warehouse_find: WarehouseResource }
     */
    public function resource(): Response
    {
        $warehouseFirst = Warehouse::latest()->first();
        $warehouseFind = Warehouse::find(1);

        return Inertia::render('Resource/Warehouse', [
            'warehouse_first' => new WarehouseResource($warehouseFirst),
            'warehouse_find' => WarehouseResource::make($warehouseFind),
        ]);
    }
}
