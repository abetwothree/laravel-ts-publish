import type { CategoryResource, CommentResource, PostResource, UserResource } from '.';

/**
 * A plain API resource nesting JSON:API resources as values. Laravel sends a single one as its resolve(), its
 * resource object under `data`, the items of its collection() bare, and each item of a named collection under `data`.
 *
 * @see Workbench\App\Http\Resources\JsonApi\NestingPlainResource
 */
export interface NestingPlainResource
{
    id: number;
    author: { data: UserResource };
    category: { data: CategoryResource } | null;
    comments: CommentResource[];
    siblings: { data: PostResource }[];
}
