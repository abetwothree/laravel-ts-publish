import { defineRoute, annotatePageProps } from '@tolki/ts';

import type { AnonymousResourceCollection, JsonResourcePaginator } from '@tolki/types';
import type { WarehouseResource } from '../resources';

export type ResourcePaginatedCollectionPageProps = Inertia.SharedData & { warehouses: JsonResourcePaginator<WarehouseResource> };

/**
 * An anonymous with paginated collection of results
 *
 * Test page type is { warehouses: JsonResourcePaginator<WarehouseResource> }
 */
export const resourcePaginatedCollection = /* @__PURE__ */ annotatePageProps<ResourcePaginatedCollectionPageProps>()(/* @__PURE__ */ defineRoute({
    name: 'collection.resource-paginated-collection',
    url: '/collection/resource-paginated-collection',
    methods: ['get', 'head'] as const,
    component: 'Resource/PaginatedWarehouse',
}));

export type ResourceAnonymousCollectionPageProps = Inertia.SharedData & { warehouse_get: AnonymousResourceCollection<WarehouseResource>, warehouse_all: AnonymousResourceCollection<WarehouseResource> };

/**
 * An anonymous with a collection of results
 *
 * Test page type is { warehouse_get: AnonymousResourceCollection<WarehouseResource>, warehouse_all: AnonymousResourceCollection<WarehouseResource> }
 */
export const resourceAnonymousCollection = /* @__PURE__ */ annotatePageProps<ResourceAnonymousCollectionPageProps>()(/* @__PURE__ */ defineRoute({
    name: 'collection.resource-anon-collection',
    url: '/collection/resource-anon-collection',
    methods: ['get', 'head'] as const,
    component: 'Resource/AnonymousWarehouse',
}));

export type ResourcePageProps = Inertia.SharedData & { warehouse_first: { data: WarehouseResource }, warehouse_find: { data: WarehouseResource } };

/**
 * Test single resource returns
 *
 * Return type is { warehouse_first: WarehouseResource, warehouse_find: WarehouseResource }
 */
export const resource = /* @__PURE__ */ annotatePageProps<ResourcePageProps>()(/* @__PURE__ */ defineRoute({
    name: 'collection.resource',
    url: '/collection/resource',
    methods: ['get', 'head'] as const,
    component: 'Resource/Warehouse',
}));

/** @see Workbench\App\Http\Controllers\InertiaSingleResourceController */
const InertiaSingleResourceController = {
    resourcePaginatedCollection,
    resourceAnonymousCollection,
    resource,
};

export default InertiaSingleResourceController;
