import { defineRoute, annotatePageProps } from '@tolki/ts';
import type { AnonymousResourceCollection, JsonResourcePaginator, ResourcePagination } from '@tolki/types';
import type { PostCollection, PostFlatCollection, PostResource } from '../resources';

export type ResourceAnonymousPaginatedPageProps = Inertia.SharedData & { posts: JsonResourcePaginator<PostResource> };

/**
  * Tests anonymous paginated result Result should be { posts: JsonResourcePaginator<PostResource> }
  */
export const resourceAnonymousPaginated = annotatePageProps<ResourceAnonymousPaginatedPageProps>()(defineRoute({
    name: 'collection.resource-anonymous-paginated',
    url: '/collection/resource-anonymous-paginated',
    methods: ['get'] as const,
    component: 'Collections/ResourceAnonymous',
}));

export type ResourceAnonymousPageProps = Inertia.SharedData & { posts: AnonymousResourceCollection<PostResource> };

/**
  * Tests anonymous result Result should be { posts: AnonymousResourceCollection<PostResource> }
  */
export const resourceAnonymous = annotatePageProps<ResourceAnonymousPageProps>()(defineRoute({
    name: 'collection.resource-anonymous',
    url: '/collection/resource-anonymous',
    methods: ['get'] as const,
    component: 'Collections/ResourceAnonymous',
}));

export type NamedCollectionPaginatedPageProps = Inertia.SharedData & { posts: PostCollection & ResourcePagination };

/**
  * Test return types with paginated named collection class Result should be { posts: PostCollection & ResourcePagination }
  */
export const namedCollectionPaginated = annotatePageProps<NamedCollectionPaginatedPageProps>()(defineRoute({
    name: 'collection.named-collection-paginated',
    url: '/collection/named-collection-paginated',
    methods: ['get'] as const,
    component: 'Collections/NamedPaginated',
}));

export type NamedPageProps = Inertia.SharedData & { posts: PostCollection };

/**
  * Test return types with named collection class Result should be { posts: PostCollection }
  */
export const named = annotatePageProps<NamedPageProps>()(defineRoute({
    name: 'collection.named',
    url: '/collection/named',
    methods: ['get'] as const,
    component: 'Collections/Named',
}));

export type FlatPaginatedPageProps = Inertia.SharedData & { posts: JsonResourcePaginator<PostResource> };

/**
  * Test a named collection class with a flat collection (no data $wrap value) Result should be { posts: JsonResourcePaginator<PostResource> }
  */
export const flatPaginated = annotatePageProps<FlatPaginatedPageProps>()(defineRoute({
    name: 'collection.flat-paginated',
    url: '/collection/flat-paginated',
    methods: ['get'] as const,
    component: 'Collections/FlatPaginated',
}));

export type FlatPageProps = Inertia.SharedData & { posts: PostFlatCollection };

/**
  * Test a named collection class with a flat collection (no data $wrap value) Using the `PostFlatCollection` works because its definition is "type PostFlatCollection = PostResource[];" Result should be { posts: PostFlatCollection }
  */
export const flat = annotatePageProps<FlatPageProps>()(defineRoute({
    name: 'collection.flat',
    url: '/collection/flat',
    methods: ['get'] as const,
    component: 'Collections/Flat',
}));

/**
 * @see Workbench\App\Http\Controllers\InertiaNamedCollectionsController
 */
const InertiaNamedCollectionsController = {
    resourceAnonymousPaginated,
    resourceAnonymous,
    namedCollectionPaginated,
    'namedCollection': named,
    'flatCollectionPaginated': flatPaginated,
    'flatCollection': flat,
};

export default InertiaNamedCollectionsController;
