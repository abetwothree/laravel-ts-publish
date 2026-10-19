import { defineRoute, annotatePageProps } from '@tolki/ts';

import type { AnonymousResourceCollection, JsonResourceCursorPaginator, JsonResourcePaginator, JsonResourceSimplePaginator, ResourceCursorPagination, ResourcePagination, ResourceSimplePagination } from '@tolki/types';
import type { PostCollection, PostResource, PostStubCollection, TeamResource, WarehouseResource } from '../resources';

export type NamedPageProps = Inertia.SharedData & { simple: PostCollection & ResourceSimplePagination, cursor: PostCollection & ResourceCursorPagination, either: PostCollection & (ResourceSimplePagination | ResourcePagination), sometimes: PostCollection | (PostCollection & ResourcePagination) };

/**
 * A wrapping collection keeps its own type beside the pagination members of each paginator it may wrap.
 *
 * Result should be { simple: PostCollection & ResourceSimplePagination,
 * cursor: PostCollection & ResourceCursorPagination, either: PostCollection & (ResourceSimplePagination |
 * ResourcePagination), sometimes: PostCollection | (PostCollection & ResourcePagination) }
 */
export const named = annotatePageProps<NamedPageProps>()(defineRoute({
    name: 'paginator-kinds.named',
    url: '/paginator-kinds/named',
    methods: ['get', 'head'] as const,
    component: 'PaginatorKinds/Named',
}));

export type AnonymousPageProps = Inertia.SharedData & { simple: JsonResourceSimplePaginator<PostResource>, cursor: JsonResourceCursorPaginator<PostResource>, sometimes: AnonymousResourceCollection<PostResource> | JsonResourceSimplePaginator<PostResource> };

/**
 * An anonymous collection over a paginator is that paginator's resource response.
 *
 * Result should be { simple: JsonResourceSimplePaginator<PostResource>,
 * cursor: JsonResourceCursorPaginator<PostResource>,
 * sometimes: AnonymousResourceCollection<PostResource> | JsonResourceSimplePaginator<PostResource> }
 */
export const anonymous = annotatePageProps<AnonymousPageProps>()(defineRoute({
    name: 'paginator-kinds.anonymous',
    url: '/paginator-kinds/anonymous',
    methods: ['get', 'head'] as const,
    component: 'PaginatorKinds/Anonymous',
}));

export type FlatPageProps = Inertia.SharedData & { simple: JsonResourceSimplePaginator<PostResource>, cursor: JsonResourceCursorPaginator<PostResource>, keyed: Omit<JsonResourceCursorPaginator<TeamResource>, 'data'> & { data: Record<string, TeamResource> } };

/**
 * A flat collection ($wrap = null) over a paginator is sent under `data`, as the paginator's resource response.
 *
 * Result should be { simple: JsonResourceSimplePaginator<PostResource>,
 * cursor: JsonResourceCursorPaginator<PostResource>,
 * keyed: Omit<JsonResourceCursorPaginator<TeamResource>, 'data'> & { data: Record<string, TeamResource> } }
 */
export const flat = annotatePageProps<FlatPageProps>()(defineRoute({
    name: 'paginator-kinds.flat',
    url: '/paginator-kinds/flat',
    methods: ['get', 'head'] as const,
    component: 'PaginatorKinds/Flat',
}));

export type GuessedPageProps = Inertia.SharedData & { named: PostCollection & ResourceSimplePagination, anonymous: JsonResourceCursorPaginator<WarehouseResource>, explicit: JsonResourcePaginator<WarehouseResource>, unpaged: PostCollection };

/**
 * toResourceCollection() builds the collection Laravel guesses, PostCollection for a post and WarehouseResource's
 * anonymous one for a warehouse, or the given resource's anonymous one, and is sent as that collection's response.
 *
 * Result should be { named: PostCollection & ResourceSimplePagination,
 * anonymous: JsonResourceCursorPaginator<WarehouseResource>, explicit: JsonResourcePaginator<WarehouseResource>,
 * unpaged: PostCollection }
 */
export const guessed = annotatePageProps<GuessedPageProps>()(defineRoute({
    name: 'paginator-kinds.guessed',
    url: '/paginator-kinds/guessed',
    methods: ['get', 'head'] as const,
    component: 'PaginatorKinds/Guessed',
}));

export type StubPagePageProps = Inertia.SharedData & { posts: PostStubCollection & ResourceSimplePagination };

/**
 * The `make:resource --collection` stub's body returns parent::toArray(), the collected list, so Laravel wraps it
 * under `data` beside the simple paginator's members, and PostStubCollection publishes that `data` key.
 *
 * Result should be { posts: PostStubCollection & ResourceSimplePagination }
 */
export const stubPage = annotatePageProps<StubPagePageProps>()(defineRoute({
    name: 'paginator-kinds.stub-page',
    url: '/paginator-kinds/stub-page',
    methods: ['get', 'head'] as const,
    component: 'PaginatorKinds/StubPage',
}));

/**
 * A resource collection over each paginator kind: Laravel's `meta` block differs for `paginate()`, `simplePaginate()`
 * and `cursorPaginate()`, so each kind publishes its own pagination type.
 *
 * @see Workbench\App\Http\Controllers\InertiaPaginatorKindsController
 */
const InertiaPaginatorKindsController = {
    named,
    anonymous,
    flat,
    guessed,
    stubPage,
};

export default InertiaPaginatorKindsController;
