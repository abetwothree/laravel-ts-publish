import { defineRoute, annotatePageProps } from '@tolki/ts';

import type { AnonymousResourceCollection, JsonResourcePaginator } from '@tolki/types';
import type { WarehouseResource } from '../resources';

export type ResourcePaginatedCollectionPageProps = Inertia.SharedData & { warehouses: JsonResourcePaginator<WarehouseResource> };

export const resourcePaginatedCollection = /* @__PURE__ */ annotatePageProps<ResourcePaginatedCollectionPageProps>()(/* @__PURE__ */ defineRoute({
    name: 'same-template.resource-paginated-collection',
    url: '/same-template/resource-paginated-collection',
    methods: ['get', 'head'] as const,
    component: 'Resource/SharedTemplate',
}));

export type ResourceAnonymousCollectionPageProps = Inertia.SharedData & { warehouse_get: AnonymousResourceCollection<WarehouseResource>, warehouse_all: AnonymousResourceCollection<WarehouseResource> };

export const resourceAnonymousCollection = /* @__PURE__ */ annotatePageProps<ResourceAnonymousCollectionPageProps>()(/* @__PURE__ */ defineRoute({
    name: 'same-template.resource-anon-collection',
    url: '/same-template/resource-anon-collection',
    methods: ['get', 'head'] as const,
    component: 'Resource/SharedTemplate',
}));

export type ResourcePageProps = Inertia.SharedData & { warehouse_first: { data: WarehouseResource }, warehouse_find: { data: WarehouseResource } };

export const resource = /* @__PURE__ */ annotatePageProps<ResourcePageProps>()(/* @__PURE__ */ defineRoute({
    name: 'same-template.resource',
    url: '/same-template/resource',
    methods: ['get', 'head'] as const,
    component: 'Resource/SharedTemplate',
}));

/**
 * The purpose is to make sure the return types are properly grouped and defined for the same template that is used across different methods.
 *
 * Result should be:
 * { warehouses: JsonResourcePaginator<WarehouseResource>, warehouse_get: AnonymousResourceCollection<WarehouseResource>, warehouse_all: AnonymousResourceCollection<WarehouseResource>, warehouse_first: WarehouseResource, warehouse_find: WarehouseResource }
 *
 * @see Workbench\App\Http\Controllers\InertiaResourceSharedTemplate
 */
const InertiaResourceSharedTemplate = {
    resourcePaginatedCollection,
    resourceAnonymousCollection,
    resource,
};

export default InertiaResourceSharedTemplate;
