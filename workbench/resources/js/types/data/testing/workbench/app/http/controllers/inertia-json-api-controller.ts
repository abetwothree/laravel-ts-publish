import { defineRoute, annotatePageProps } from '@tolki/ts';

import type { JsonApiCollection, JsonApiCursorPaginator, JsonApiDocument, JsonApiPaginator, JsonApiResourceObject, JsonApiSimplePaginator } from '@tolki/types';
import type { CategoryResource, CommentResource, KpiResource, PostResource, UserResource } from '../resources/json-api';

export type ShowPageProps = Inertia.SharedData & { post: JsonApiDocument<PostResource, UserResource | CommentResource | CategoryResource>, author: JsonApiDocument<UserResource, never>, category: JsonApiDocument<CategoryResource, never> | null, kpi: JsonApiDocument<KpiResource, JsonApiResourceObject<'reports'>> };

/**
 * A single resource object's document, whose `included` holds every resource an include can reach.
 *
 * Result should be { post: JsonApiDocument<PostResource, UserResource | CommentResource | CategoryResource>,
 * author: JsonApiDocument<UserResource, never>, category: JsonApiDocument<CategoryResource, never> | null,
 * kpi: JsonApiDocument<KpiResource, JsonApiResourceObject<'reports'>> }
 */
export const show = annotatePageProps<ShowPageProps>()(defineRoute({
    name: 'json-api.show',
    url: '/json-api/posts/{post}',
    methods: ['get', 'head'] as const,
    args: [{name: 'post', required: true, _routeKey: 'id'}] as const,
    component: 'JsonApi/Show',
}));

export type IndexPageProps = Inertia.SharedData & { posts: JsonApiCollection<PostResource, UserResource | CommentResource | CategoryResource>, paged: JsonApiPaginator<PostResource, UserResource | CommentResource | CategoryResource>, simple: JsonApiSimplePaginator<PostResource, UserResource | CommentResource | CategoryResource>, cursor: JsonApiCursorPaginator<PostResource, UserResource | CommentResource | CategoryResource>, comments: JsonApiCollection<CommentResource, UserResource> };

/**
 * A collection's document over a list and over each paginator kind.
 *
 * Result should be { posts: JsonApiCollection<PostResource, UserResource | CommentResource | CategoryResource>,
 * paged: JsonApiPaginator<…>, simple: JsonApiSimplePaginator<…>, cursor: JsonApiCursorPaginator<…>,
 * comments: JsonApiCollection<CommentResource, UserResource> }
 */
export const index = annotatePageProps<IndexPageProps>()(defineRoute({
    name: 'json-api.index',
    url: '/json-api/posts',
    methods: ['get', 'head'] as const,
    component: 'JsonApi/Index',
}));

/**
 * JSON:API resources as Inertia props, which Inertia sends as each one's whole document. Its routes are registered
 * only on a Laravel that ships JsonApiResource, 12.45 and later.
 *
 * @see Workbench\App\Http\Controllers\InertiaJsonApiController
 */
const InertiaJsonApiController = {
    show,
    index,
};

export default InertiaJsonApiController;
