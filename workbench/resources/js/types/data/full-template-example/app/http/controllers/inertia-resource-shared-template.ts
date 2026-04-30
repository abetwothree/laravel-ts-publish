import { defineRoute, annotatePageProps } from '@tolki/ts';
import type { AnonymousResourceCollection, JsonResourcePaginator } from '@tolki/types';
import type { WarehouseResource } from '../resources';

export type ResourcePaginatedCollectionPageProps = Inertia.SharedData & { warehouses: JsonResourcePaginator<WarehouseResource> };

export const resourcePaginatedCollection = annotatePageProps<ResourcePaginatedCollectionPageProps>()(defineRoute({
    name: 'same-template.resource-paginated-collection',
    url: '/same-template/resource-paginated-collection',
    methods: ['get'] as const,
    component: 'Resource/SharedTemplate',
}));

export type ResourceAnonCollectionPageProps = Inertia.SharedData & { warehouse_get: AnonymousResourceCollection<WarehouseResource>, warehouse_all: AnonymousResourceCollection<WarehouseResource> };

export const resourceAnonCollection = annotatePageProps<ResourceAnonCollectionPageProps>()(defineRoute({
    name: 'same-template.resource-anon-collection',
    url: '/same-template/resource-anon-collection',
    methods: ['get'] as const,
    component: 'Resource/SharedTemplate',
}));

export type ResourcePageProps = Inertia.SharedData & { warehouse_first: WarehouseResource, warehouse_find: WarehouseResource };

export const resource = annotatePageProps<ResourcePageProps>()(defineRoute({
    name: 'same-template.resource',
    url: '/same-template/resource',
    methods: ['get'] as const,
    component: 'Resource/SharedTemplate',
}));

/**
 * The purpose is to make sure the return types are properly grouped and defined for the same template that is used across different methods. Result should be: { warehouses: JsonResourcePaginator<WarehouseResource>, warehouse_get: AnonymousResourceCollection<WarehouseResource>, warehouse_all: AnonymousResourceCollection<WarehouseResource>, warehouse_first: WarehouseResource, warehouse_find: WarehouseResource }
 *
 * @see Workbench\App\Http\Controllers\InertiaResourceSharedTemplate
 */
const InertiaResourceSharedTemplate = {
    resourcePaginatedCollection,
    'resourceAnonymousCollection': resourceAnonCollection,
    resource,
};

export default InertiaResourceSharedTemplate;
