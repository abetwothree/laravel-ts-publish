import { defineRoute, annotatePageProps } from '@tolki/ts';
import type { AnonymousResourceCollection, JsonResourcePaginator } from '@tolki/types';
import type { WarehouseResource } from '../resources';

export type ResourcePaginatedCollectionPageProps = Inertia.SharedData & { warehouses: JsonResourcePaginator<WarehouseResource> };

/**
  * An anonymous with paginated collection of results Test page type is { warehouses: JsonResourcePaginator<WarehouseResource> }
  */
export const resourcePaginatedCollection = annotatePageProps<ResourcePaginatedCollectionPageProps>()(defineRoute({
    name: 'collection.resource-paginated-collection',
    url: '/collection/resource-paginated-collection',
    methods: ['get'] as const,
    component: 'Resource/PaginatedWarehouse',
}));

export type ResourceAnonCollectionPageProps = Inertia.SharedData & { warehouse_get: AnonymousResourceCollection<WarehouseResource>, warehouse_all: AnonymousResourceCollection<WarehouseResource> };

/**
  * An anonymous with a collection of results Test page type is { warehouse_get: AnonymousResourceCollection<WarehouseResource>, warehouse_all: AnonymousResourceCollection<WarehouseResource> }
  */
export const resourceAnonCollection = annotatePageProps<ResourceAnonCollectionPageProps>()(defineRoute({
    name: 'collection.resource-anon-collection',
    url: '/collection/resource-anon-collection',
    methods: ['get'] as const,
    component: 'Resource/AnonymousWarehouse',
}));

export type ResourcePageProps = Inertia.SharedData & { warehouse_first: WarehouseResource, warehouse_find: WarehouseResource };

/**
  * Test single resource returns Return type is { warehouse_first: WarehouseResource, warehouse_find: WarehouseResource }
  */
export const resource = annotatePageProps<ResourcePageProps>()(defineRoute({
    name: 'collection.resource',
    url: '/collection/resource',
    methods: ['get'] as const,
    component: 'Resource/Warehouse',
}));

/**
 * @see Workbench\App\Http\Controllers\InertiaSingleResourceController
 */
const InertiaSingleResourceController = {
    resourcePaginatedCollection,
    'resourceAnonymousCollection': resourceAnonCollection,
    resource,
};

export default InertiaSingleResourceController;
