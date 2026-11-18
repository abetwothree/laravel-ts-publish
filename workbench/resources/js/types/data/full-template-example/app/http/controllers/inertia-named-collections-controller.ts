import { defineRoute, annotatePageProps } from '@tolki/ts';

import type { AnonymousResourceCollection, JsonResourcePaginator, ResourcePagination } from '@tolki/types';
import type { PostCollection, PostFlatCollection, PostResource } from '../resources';

export type ResourceAnonymousPaginatedPageProps = Inertia.SharedData & { posts: JsonResourcePaginator<PostResource> };

/**
 * Tests anonymous paginated result
 *
 * Result should be { posts: JsonResourcePaginator<PostResource> }
 */
export const resourceAnonymousPaginated = /* @__PURE__ */ annotatePageProps<ResourceAnonymousPaginatedPageProps>()(/* @__PURE__ */ defineRoute({
    name: 'collection.resource-anonymous-paginated',
    url: '/collection/resource-anonymous-paginated',
    methods: ['get', 'head'] as const,
    component: 'Collections/ResourceAnonymous',
}));

export type ResourceAnonymousPageProps = Inertia.SharedData & { posts: AnonymousResourceCollection<PostResource> };

/**
 * Tests anonymous result
 *
 * Result should be { posts: AnonymousResourceCollection<PostResource> }
 */
export const resourceAnonymous = /* @__PURE__ */ annotatePageProps<ResourceAnonymousPageProps>()(/* @__PURE__ */ defineRoute({
    name: 'collection.resource-anonymous',
    url: '/collection/resource-anonymous',
    methods: ['get', 'head'] as const,
    component: 'Collections/ResourceAnonymous',
}));

export type NamedCollectionPaginatedPageProps = Inertia.SharedData & { posts: PostCollection & ResourcePagination };

/**
 * Test return types with paginated named collection class
 *
 * Result should be { posts: PostCollection & ResourcePagination }
 */
export const namedCollectionPaginated = /* @__PURE__ */ annotatePageProps<NamedCollectionPaginatedPageProps>()(/* @__PURE__ */ defineRoute({
    name: 'collection.named-collection-paginated',
    url: '/collection/named-collection-paginated',
    methods: ['get', 'head'] as const,
    component: 'Collections/NamedPaginated',
}));

export type NamedCollectionPageProps = Inertia.SharedData & { posts: PostCollection };

/**
 * Test return types with named collection class
 *
 * Result should be { posts: PostCollection }
 */
export const namedCollection = /* @__PURE__ */ annotatePageProps<NamedCollectionPageProps>()(/* @__PURE__ */ defineRoute({
    name: 'collection.named',
    url: '/collection/named',
    methods: ['get', 'head'] as const,
    component: 'Collections/Named',
}));

export type FlatCollectionPaginatedPageProps = Inertia.SharedData & { posts: JsonResourcePaginator<PostResource> };

/**
 * Test a named collection class with a flat collection (no data $wrap value)
 *
 * Result should be { posts: JsonResourcePaginator<PostResource> }
 */
export const flatCollectionPaginated = /* @__PURE__ */ annotatePageProps<FlatCollectionPaginatedPageProps>()(/* @__PURE__ */ defineRoute({
    name: 'collection.flat-paginated',
    url: '/collection/flat-paginated',
    methods: ['get', 'head'] as const,
    component: 'Collections/FlatPaginated',
}));

export type FlatCollectionPageProps = Inertia.SharedData & { posts: PostFlatCollection };

/**
 * Test a named collection class with a flat collection (no data $wrap value)
 *
 * Using the `PostFlatCollection` works because its definition is "type PostFlatCollection = PostResource[];"
 *
 * Result should be { posts: PostFlatCollection }
 */
export const flatCollection = /* @__PURE__ */ annotatePageProps<FlatCollectionPageProps>()(/* @__PURE__ */ defineRoute({
    name: 'collection.flat',
    url: '/collection/flat',
    methods: ['get', 'head'] as const,
    component: 'Collections/Flat',
}));

/** @see Workbench\App\Http\Controllers\InertiaNamedCollectionsController */
const InertiaNamedCollectionsController = {
    resourceAnonymousPaginated,
    resourceAnonymous,
    namedCollectionPaginated,
    namedCollection,
    flatCollectionPaginated,
    flatCollection,
};

export default InertiaNamedCollectionsController;
