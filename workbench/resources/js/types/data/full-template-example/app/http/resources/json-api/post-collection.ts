import type { PostResource } from '.';

/**
 * A named collection of JSON:API resources: ResourceCollection's toArray() sends each item's resolve(), its resource
 * object under `data`.
 *
 * @see Workbench\App\Http\Resources\JsonApi\PostCollection
 */
export interface PostCollection
{
    data: { data: PostResource }[];
}
